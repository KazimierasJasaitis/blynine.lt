<?php
declare(strict_types=1);

namespace Blynine;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/** Changes to migration or seed inputs trigger a single, serialized initialization pass. */
function bootstrap_fingerprint(): string
{
    static $fingerprint;
    if (is_string($fingerprint)) {
        return $fingerprint;
    }
    $files = glob(dirname(__DIR__) . '/migrations/*.sql');
    if ($files === false || $files === []) {
        throw new \RuntimeException('Database migrations are missing.');
    }
    sort($files);
    $parts = ['blynine-bootstrap-v1'];
    foreach (array_merge($files, [__DIR__ . '/seed-data.php']) as $file) {
        $hash = is_file($file) ? hash_file('sha256', $file) : false;
        if (!is_string($hash)) {
            throw new \RuntimeException('Database initialization files could not be read.');
        }
        $parts[] = basename($file) . ':' . $hash;
    }
    return $fingerprint = hash('sha256', implode("\n", $parts));
}

function bootstrap_is_current(\PDO $pdo, string $fingerprint): bool
{
    try {
        $statement = $pdo->prepare('SELECT meta_value FROM blynine_metadata WHERE meta_key = ?');
        $statement->execute(['bootstrap_fingerprint']);
        $stored = $statement->fetchColumn();
        return is_string($stored) && hash_equals($fingerprint, $stored);
    } catch (\PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1146) {
            return false; // An empty database is expected on the first visit.
        }
        throw $error;
    }
}

/**
 * No DDL or seed writes occur on ordinary requests once the fingerprint matches.
 * MySQL locks survive DDL's implicit commits and serialize concurrent first visits.
 * @return array{changed: bool, migrations: array, seeded: int}
 */
function ensure_initialized(\PDO $pdo, bool $force = false): array
{
    $fingerprint = bootstrap_fingerprint();
    $unchanged = ['changed' => false, 'migrations' => [], 'seeded' => 0];
    if (!$force && bootstrap_is_current($pdo, $fingerprint)) {
        return $unchanged;
    }
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'blynine-bootstrap:' . substr(hash('sha256', $database), 0, 40);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new \RuntimeException('Database initialization is busy. Try again shortly.');
    }
    try {
        // Another visitor may have completed setup while this connection waited.
        if (!$force && bootstrap_is_current($pdo, $fingerprint)) {
            return $unchanged;
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->exec('CREATE TABLE IF NOT EXISTS blynine_metadata (meta_key VARCHAR(80) NOT NULL PRIMARY KEY, meta_value CHAR(64) NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=ascii');
        $files = glob(dirname(__DIR__) . '/migrations/*.sql');
        sort($files);
        $applied = [];
        $check = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = ?');
        $mark = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP())');
        foreach ($files as $file) {
            $version = basename($file);
            $check->execute([$version]);
            if ($check->fetchColumn() !== false) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('Database migration could not be read.');
            }
            // Migration files contain standalone idempotent statements, without stored procedures.
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
            }
            $mark->execute([$version]);
            $applied[] = $version;
        }
        $seeded = seed_dictionary($pdo);
        // This marker is written only after all migrations and the seed transaction succeed.
        $statement = $pdo->prepare('INSERT INTO blynine_metadata (meta_key, meta_value, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = UTC_TIMESTAMP()');
        $statement->execute(['bootstrap_fingerprint', $fingerprint]);
        return ['changed' => true, 'migrations' => $applied, 'seeded' => $seeded];
    } finally {
        // Preserve the original setup error if releasing a lost connection also fails.
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (\Throwable $error) {
            error_log('blynine bootstrap lock release failed: ' . get_class($error));
        }
    }
}

/** Insert starter rows atomically, preserving existing tags, entries, edits and statuses. */
function seed_dictionary(\PDO $pdo): int
{
    $data = require __DIR__ . '/seed-data.php';
    if (!is_array($data) || !is_array($data['tags'] ?? null) || !is_array($data['entries'] ?? null)) {
        throw new \RuntimeException('Starter vocabulary is invalid.');
    }
    $pdo->beginTransaction();
    try {
        $tagIds = [];
        $findTag = $pdo->prepare('SELECT id FROM dictionary_tags WHERE slug = ? OR label = ? ORDER BY (slug = ?) DESC LIMIT 1');
        $insertTag = $pdo->prepare('INSERT INTO dictionary_tags (slug, label) VALUES (?, ?)');
        foreach ($data['tags'] as $slug => $label) {
            $findTag->execute([$slug, $label, $slug]);
            $id = $findTag->fetchColumn();
            if ($id === false) {
                $insertTag->execute([$slug, $label]);
                $id = $pdo->lastInsertId();
            }
            $tagIds[$slug] = (int) $id;
        }
        $check = $pdo->prepare('SELECT id FROM dictionary_entries WHERE seed_key = ?');
        $insert = $pdo->prepare("INSERT INTO dictionary_entries (word, meaning, example, status, seed_key, created_at, updated_at, reviewed_at) VALUES (?, ?, ?, 'approved', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $insertJoin = $pdo->prepare('INSERT INTO dictionary_entry_tags (entry_id, tag_id) VALUES (?, ?)');
        $inserted = 0;
        foreach ($data['entries'] as [$key, $word, $meaning, $example, $entryTags]) {
            $seedKey = 'v1-' . $key;
            $check->execute([$seedKey]);
            if ($check->fetchColumn() !== false) {
                continue;
            }
            $insert->execute([$word, $meaning, $example, $seedKey]);
            $id = (int) $pdo->lastInsertId();
            foreach ($entryTags as $slug) {
                if (!isset($tagIds[$slug])) {
                    throw new \RuntimeException('Starter vocabulary references an unknown tag.');
                }
                $insertJoin->execute([$id, $tagIds[$slug]]);
            }
            $inserted++;
        }
        $pdo->commit();
        return $inserted;
    } catch (\Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
