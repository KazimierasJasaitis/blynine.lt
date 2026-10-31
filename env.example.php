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
    // Generate with: php scripts/create-admin.php
    'APP_SECRET' => '', // At least 32 random characters.
    'ADMIN_USERNAME' => '',
    'ADMIN_PASSWORD_HASH' => '', // password_hash() output; never a plaintext password.
    'SENDER_API_TOKEN' => '',
    'SENDER_GROUP_IDS' => [], // ['your-group-id']
    'SENDER_TRIGGER_AUTOMATION' => true,
];
