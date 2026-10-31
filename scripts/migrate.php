<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/lib/app.php';

try {
    $pdo = \Blynine\db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $files = glob(dirname(__DIR__) . '/migrations/*.sql');
    sort($files);
    foreach ($files as $file) {
        $version = basename($file);
        $check = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = ?');
        $check->execute([$version]);
        if ($check->fetchColumn() !== false) {
            fwrite(STDOUT, "Already applied: {$version}\n");
            continue;
        }
        // MySQL DDL commits implicitly; migrations use idempotent statements so interrupted runs are safe.
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Migration could not be read.');
        }
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
        $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP())')->execute([$version]);
        fwrite(STDOUT, "Applied: {$version}\n");
    }
    fwrite(STDOUT, "Migrations complete.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Migration failed. Check the configuration, database access, and PHP error log.\n");
    error_log('blynine migration: ' . $error->getMessage());
    exit(1);
}
