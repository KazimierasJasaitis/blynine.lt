<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/lib/app.php';

try {
    // Connection initialization applies migrations and starter vocabulary automatically.
    \Blynine\db();
    fwrite(STDOUT, "Database ready. Migrations and starter vocabulary are initialized automatically.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Initialization failed. Check the environment file, database access, and PHP error log.\n");
    error_log('blynine initialization: ' . get_class($error));
    exit(1);
}
