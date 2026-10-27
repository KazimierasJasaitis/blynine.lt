<?php
declare(strict_types=1);

namespace Blynine\Sender;

function subscribe(string $email): bool
{
    try {
        $configPath = dirname(__DIR__) . '/env.local.php';
        $config = is_file($configPath) ? require $configPath : [];
        if (!is_array($config)) {
            return false;
        }

        $token = $config['SENDER_API_TOKEN'] ?? '';
        if (!is_string($token) || trim($token) === '' || preg_match('/[\r\n]/', $token)) {
            error_log('Sender signup: API token is not configured correctly.');
            return false;
        }
        if (!function_exists('curl_init')) {
            error_log('Sender signup: the PHP cURL extension is required.');
            return false;
        }

        $groups = $config['SENDER_GROUP_IDS'] ?? [];
        $triggerAutomation = $config['SENDER_TRIGGER_AUTOMATION'] ?? true;
        if (!is_array($groups) || !is_bool($triggerAutomation)) {
            return false;
        }
        foreach ($groups as &$group) {
            if (!is_string($group) || trim($group) === '') {
                return false;
            }
            $group = trim($group);
        }
        unset($group);

        $payload = ['email' => $email, 'trigger_automation' => $triggerAutomation];
        if ($groups !== []) {
            $payload['groups'] = array_values(array_unique($groups));
        }

        $handle = curl_init('https://api.sender.net/v2/subscribers');
        if ($handle === false || !curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim($token),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ])) {
            return false;
        }

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if (!is_string($response) || $status < 200 || $status >= 300) {
            error_log('Sender signup: request failed (HTTP ' . $status . ', cURL ' . curl_errno($handle) . ').');
            return false;
        }

        $result = json_decode($response, true);
        return is_array($result) && ($result['success'] ?? false) === true;
    } catch (\Throwable $error) {
        error_log('Sender signup: request could not be completed (' . get_class($error) . ' in ' . basename($error->getFile()) . ':' . $error->getLine() . ').');
        return false;
    }
}
