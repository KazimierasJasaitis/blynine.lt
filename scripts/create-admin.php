<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/lib/app.php';

function admin_read(string $prompt, bool $hidden = false): string
{
    fwrite(STDOUT, $prompt);
    $tty = function_exists('stream_isatty') && stream_isatty(STDIN);
    $echoDisabled = false;
    if ($hidden && $tty) {
        if (!function_exists('exec')) {
            throw new RuntimeException('Hidden password input is unavailable. Pipe username, password and confirmation through stdin instead.');
        }
        exec('stty -echo 2>/dev/null', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Cannot hide password input. Pipe username, password and confirmation through stdin instead.');
        }
        $echoDisabled = true;
    }
    try {
        $line = fgets(STDIN);
    } finally {
        if ($echoDisabled) {
            exec('stty echo 2>/dev/null');
            fwrite(STDOUT, "\n");
        }
    }
    if ($line === false) {
        throw new RuntimeException('Input ended before configuration was complete.');
    }
    return rtrim($line, "\r\n");
}

try {
    fwrite(STDOUT, "Generate settings for the single administrator account. No files are changed; the password is never printed.\n");
    $username = trim(admin_read('Administrator username: '));
    $password = admin_read('Password (at least 12 characters, at most 72 bytes): ', true);
    $confirmation = admin_read('Repeat password: ', true);
    if ($username === '' || mb_strlen($username) > 80 || preg_match('/[\x00-\x1F\x7F]/u', $username)) {
        throw new RuntimeException('Username must contain 1–80 characters without control characters.');
    }
    if (mb_strlen($password) < 12 || strlen($password) > 72 || $password !== $confirmation) {
        throw new RuntimeException('Passwords must match and contain at least 12 characters and at most 72 bytes.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    unset($password, $confirmation);
    $secret = bin2hex(random_bytes(32));
    fwrite(STDOUT, "\nMerge these settings into ignored env.local.php; preserve its database and Sender settings.\n");
    fwrite(STDOUT, "    'ADMIN_USERNAME' => " . var_export($username, true) . ",\n");
    fwrite(STDOUT, "    'ADMIN_PASSWORD_HASH' => " . var_export($hash, true) . ",\n");
    fwrite(STDOUT, "    'APP_SECRET' => " . var_export($secret, true) . ",\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Setup failed: ' . $error->getMessage() . "\n");
    exit(1);
}
