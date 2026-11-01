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
    // Legacy PHP configuration is still supported. New deployments can use .env.example.
    'ADMIN_USERNAME' => 'admin',
    'ADMIN_PASSWORD' => '', // At least 12 characters; leave blank when using a hash below.
    'ADMIN_PASSWORD_HASH' => '', // Optional existing password_hash() output.
    'APP_SECRET' => '', // Optional; otherwise derived automatically from private credentials.
    'SENDER_API_TOKEN' => '',
    'SENDER_GROUP_IDS' => [], // ['your-group-id']
    'SENDER_TRIGGER_AUTOMATION' => true,
];
