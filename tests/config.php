<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

// Isolated, local-only fixtures. Never use this configuration on a public host.
return [
    'DB_HOST' => getenv('BLYNINE_TEST_DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => (int) (getenv('BLYNINE_TEST_DB_PORT') ?: 33079),
    'DB_NAME' => 'blynine_test',
    'DB_USER' => 'blynine_test',
    'DB_PASS' => 'local-test-only',
    'ADMIN_USERNAME' => 'test-admin',
    'ADMIN_PASSWORD_HASH' => '$2y$12$nZ.qQGMfHG7lWUtbAG5doOiE1juHdUg4/xO.yv0lllTik.lRGkSSa',
    'APP_SECRET' => 'local-test-only-change-this-on-a-real-host-64-characters-minimum-123456',
];
