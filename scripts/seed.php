<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/lib/app.php';

try {
    $pdo = \Blynine\db();
    // A manual rerun remains available for maintenance; all existing rows are preserved.
    $result = \Blynine\ensure_initialized($pdo, true);
    fwrite(STDOUT, 'Seed complete: ' . $result['seeded'] . " new entries; existing entries preserved.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Initialization failed. Check the environment file, database access, and PHP error log.\n");
    error_log('blynine seed: ' . get_class($error));
    exit(1);
}
