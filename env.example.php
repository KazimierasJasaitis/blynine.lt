<?php
declare(strict_types=1);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

return [
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
    'SENDER_API_TOKEN' => '',
    'SENDER_GROUP_IDS' => [], // ['your-group-id']
    'SENDER_TRIGGER_AUTOMATION' => true,
];
