<?php
declare(strict_types=1);

namespace Blynine;

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

/** A small literal dotenv reader: never evaluates shell syntax or expands variables. */
function load_dotenv_file(string $path): array
{
    $contents = @file_get_contents($path);
    if ($contents === false) {
        throw new \RuntimeException('Environment configuration could not be read.');
    }
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
    $config = [];
    foreach (preg_split('/\r\n|\n|\r/', $contents) as $index => $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/D', $line, $match) || isset($config[$match[1]])) {
            throw new \RuntimeException('Invalid environment configuration on line ' . ($index + 1) . '.');
        }
        $key = $match[1];
        $raw = $match[2];
        $value = '';
        if ($raw !== '' && ($raw[0] === '"' || $raw[0] === "'")) {
            $quote = $raw[0];
            $closed = false;
            $length = strlen($raw);
            for ($position = 1; $position < $length; $position++) {
                $character = $raw[$position];
                if ($character === '\\' && $position + 1 < $length
                    && ($raw[$position + 1] === $quote || $raw[$position + 1] === '\\')) {
                    $value .= $raw[++$position];
                } elseif ($character === $quote) {
                    $trailing = trim(substr($raw, $position + 1));
                    if ($trailing !== '' && !str_starts_with($trailing, '#')) {
                        throw new \RuntimeException('Invalid environment configuration on line ' . ($index + 1) . '.');
                    }
                    $closed = true;
                    break;
                } else {
                    $value .= $character;
                }
            }
            if (!$closed) {
                throw new \RuntimeException('Unclosed environment value on line ' . ($index + 1) . '.');
            }
        } else {
            // An unquoted # is a comment only at the start or following whitespace.
            $value = str_starts_with($raw, '#') ? '' : trim((string) preg_replace('/\s+#.*$/', '', $raw));
        }
        $config[$key] = $value;
    }
    return $config;
}

function app_config(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }
    $override = getenv('BLYNINE_CONFIG');
    if (is_string($override) && $override !== '') {
        $path = $override;
    } else {
        $path = is_file(dirname(__DIR__) . '/.env') ? dirname(__DIR__) . '/.env' : dirname(__DIR__) . '/env.local.php';
    }
    if (!str_starts_with($path, '/') || !is_file($path)) {
        throw new \RuntimeException('Server configuration is missing.');
    }
    $loaded = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php' ? require $path : load_dotenv_file($path);
    if (!is_array($loaded)) {
        throw new \RuntimeException('Server configuration is invalid.');
    }
    $loaded += ['DB_HOST' => 'localhost', 'DB_PORT' => 3306, 'ADMIN_USERNAME' => 'admin'];
    if (is_string($loaded['ADMIN_USERNAME'])) {
        $loaded['ADMIN_USERNAME'] = trim($loaded['ADMIN_USERNAME']);
    }
    if (isset($loaded['SENDER_GROUP_IDS']) && is_string($loaded['SENDER_GROUP_IDS'])) {
        $loaded['SENDER_GROUP_IDS'] = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $loaded['SENDER_GROUP_IDS'])), static fn (string $id): bool => $id !== ''
        )));
    }
    if (isset($loaded['SENDER_TRIGGER_AUTOMATION']) && is_string($loaded['SENDER_TRIGGER_AUTOMATION'])) {
        $boolean = strtolower(trim($loaded['SENDER_TRIGGER_AUTOMATION']));
        if (!in_array($boolean, ['true', 'false', '1', '0'], true)) {
            throw new \RuntimeException('SENDER_TRIGGER_AUTOMATION must be true or false.');
        }
        $loaded['SENDER_TRIGGER_AUTOMATION'] = in_array($boolean, ['true', '1'], true);
    }
    $plain = $loaded['ADMIN_PASSWORD'] ?? '';
    if (!is_string($plain) || ($plain !== '' && (mb_strlen($plain) < 12 || strlen($plain) > 128 || str_contains($plain, "\0")))) {
        throw new \RuntimeException('ADMIN_PASSWORD must have at least 12 characters and at most 128 bytes.');
    }
    $hash = $loaded['ADMIN_PASSWORD_HASH'] ?? '';
    $hashValid = is_string($hash) && (password_get_info($hash)['algoName'] ?? 'unknown') !== 'unknown';
    $secret = $loaded['APP_SECRET'] ?? '';
    if (!is_string($secret) || ($secret !== '' && strlen($secret) < 32)) {
        throw new \RuntimeException('APP_SECRET must have at least 32 characters when supplied.');
    }
    if ($secret === '') {
        $databasePassword = $loaded['DB_PASS'] ?? '';
        if ($plain === '' && !$hashValid && (!is_string($databasePassword) || strlen($databasePassword) < 12)) {
            throw new \RuntimeException('Private credentials are required to derive the application secret.');
        }
        // Stable private input avoids writable application files and a separate setup secret.
        $material = json_encode([
            $loaded['DB_HOST'] ?? '', $loaded['DB_PORT'] ?? 3306,
            $loaded['DB_NAME'] ?? '', $loaded['DB_USER'] ?? '', $databasePassword,
            $loaded['ADMIN_USERNAME'], $plain !== '' ? $plain : $hash,
        ], JSON_THROW_ON_ERROR);
        $loaded['APP_SECRET'] = hash_hkdf('sha256', $material, 32, 'blynine/app-secret/v1', 'blynine/private-config/v1');
        $loaded['APP_SECRET'] = bin2hex($loaded['APP_SECRET']);
    }
    return $config = $loaded;
}

function db(): \PDO
{
    static $connection;
    if ($connection instanceof \PDO) {
        return $connection;
    }
    $config = app_config();
    $host = $config['DB_HOST'] ?? '';
    $name = $config['DB_NAME'] ?? '';
    $user = $config['DB_USER'] ?? '';
    $password = $config['DB_PASS'] ?? '';
    $port = $config['DB_PORT'] ?? 3306;
    if (is_string($port) && ctype_digit($port)) {
        $port = (int) $port;
    }
    if (!is_string($host) || !preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $host)
        || !is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name)
        || !is_string($user) || $user === '' || !is_string($password)
        || !is_int($port) || $port < 1 || $port > 65535) {
        throw new \RuntimeException('Database configuration is invalid.');
    }
    $pdo = new \PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
    );
    $pdo->exec("SET time_zone = '+00:00'");
    require_once __DIR__ . '/bootstrap.php';
    ensure_initialized($pdo);
    return $connection = $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('BLYNINESESSID');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    if (!session_start()) {
        throw new \RuntimeException('Session could not be started.');
    }
    if (isset($_SESSION['admin']) && (int) ($_SESSION['admin_last_activity'] ?? 0) < time() - 1800) {
        unset($_SESSION['admin'], $_SESSION['admin_last_activity'], $_SESSION['admin_fingerprint'], $_SESSION['csrf']);
        session_regenerate_id(true);
    }
    if (isset($_SESSION['admin'])) {
        $_SESSION['admin_last_activity'] = time();
    }
}

function csrf_token(): string
{
    start_session();
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(?string $token): bool
{
    start_session();
    return is_string($token) && isset($_SESSION['csrf']) && is_string($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function flash(string $kind, string $message, array $old = [], array $errors = []): void
{
    start_session();
    $_SESSION['flash'] = compact('kind', 'message', 'old', 'errors');
}

function take_flash(): ?array
{
    start_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

/** Each SQL LIKE wildcard is escaped with '=' so searches treat user input literally. */
function dictionary_data(string $query = '', string $tag = '', int $page = 1): array
{
    $pdo = db();
    $query = mb_substr(trim($query), 0, 100);
    $tag = trim($tag);
    $conditions = ["e.status = 'approved'"];
    $parameters = [];
    if ($query !== '') {
        $search = '%' . str_replace(['=', '%', '_'], ['==', '=%', '=_'], $query) . '%';
        $conditions[] = "(e.word LIKE ? ESCAPE '=' OR e.meaning LIKE ? ESCAPE '=' OR e.example LIKE ? ESCAPE '=')";
        array_push($parameters, $search, $search, $search);
    }
    if ($tag !== '') {
        $conditions[] = 'EXISTS (SELECT 1 FROM dictionary_entry_tags et INNER JOIN dictionary_tags t ON t.id = et.tag_id WHERE et.entry_id = e.id AND t.slug = ?)';
        $parameters[] = $tag;
    }
    $where = implode(' AND ', $conditions);
    $count = $pdo->prepare('SELECT COUNT(*) FROM dictionary_entries e WHERE ' . $where);
    $count->execute($parameters);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 18));
    $page = min($pages, max(1, $page));
    $offset = ($page - 1) * 18;
    $statement = $pdo->prepare('SELECT e.id, e.word, e.meaning, e.example FROM dictionary_entries e WHERE ' . $where . ' ORDER BY e.word, e.id LIMIT 18 OFFSET ' . $offset);
    $statement->execute($parameters);
    $entries = $statement->fetchAll();
    foreach ($entries as &$entry) {
        $entry['id'] = (int) $entry['id'];
        $entry['tags'] = [];
    }
    unset($entry);
    $tags = all_tags();
    if ($entries !== []) {
        $ids = array_column($entries, 'id');
        $join = $pdo->prepare('SELECT et.entry_id, t.id, t.slug, t.label FROM dictionary_entry_tags et INNER JOIN dictionary_tags t ON t.id = et.tag_id WHERE et.entry_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY t.label');
        $join->execute($ids);
        $entryPositions = array_flip($ids);
        foreach ($join->fetchAll() as $tagRow) {
            $entryId = (int) $tagRow['entry_id'];
            unset($tagRow['entry_id']);
            $tagRow['id'] = (int) $tagRow['id'];
            $entries[$entryPositions[$entryId]]['tags'][] = $tagRow;
        }
    }
    return compact('entries', 'tags', 'total', 'page', 'pages');
}

function all_tags(): array
{
    $tags = db()->query('SELECT id, slug, label FROM dictionary_tags ORDER BY label')->fetchAll();
    foreach ($tags as &$tag) {
        $tag['id'] = (int) $tag['id'];
    }
    return $tags;
}

function post_string(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

/** Validate public and administrator entry forms using the same constraints. */
function entry_input(array $input): array
{
    $values = [];
    foreach (['word', 'meaning', 'example'] as $field) {
        $values[$field] = isset($input[$field]) && is_string($input[$field]) ? trim($input[$field]) : '';
    }
    $values['tags'] = [];
    $errors = [];
    foreach (['word' => 120, 'meaning' => 1200, 'example' => 600] as $field => $maximum) {
        if (($field !== 'example' && $values[$field] === '') || mb_strlen($values[$field]) > $maximum) {
            $errors[$field] = match ($field) {
                'word' => 'Įrašyk keiksmažodį (iki 120 ženklų).',
                'meaning' => 'Paaiškink reikšmę (iki 1200 ženklų).',
                default => 'Pavyzdys turi būti ne ilgesnis kaip 600 ženklų.',
            };
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $values[$field])) {
            $errors[$field] = 'Pašalink neleistinus valdymo ženklus.';
        }
    }
    $rawTags = $input['tags'] ?? [];
    if (!is_array($rawTags) || count($rawTags) > 12) {
        $errors['tags'] = 'Pasirink iki 12 žymų.';
        $rawTags = [];
    }
    $validTags = array_column(all_tags(), 'id');
    foreach ($rawTags as $rawTag) {
        if ((!is_string($rawTag) && !is_int($rawTag)) || !ctype_digit((string) $rawTag)
            || !in_array((int) $rawTag, $validTags, true)) {
            $errors['tags'] = 'Pasirink žymas iš sąrašo.';
            continue;
        }
        $values['tags'][] = (int) $rawTag;
    }
    $values['tags'] = array_values(array_unique($values['tags']));
    return [$values, $errors];
}

function save_entry(?int $id, array $values, string $status, ?string $seedKey = null): int
{
    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        throw new \InvalidArgumentException('Invalid status.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $statement = $pdo->prepare('INSERT INTO dictionary_entries (word, meaning, example, status, seed_key, created_at, updated_at, reviewed_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)');
            $statement->execute([$values['word'], $values['meaning'], $values['example'], $status, $seedKey, $status === 'pending' ? null : gmdate('Y-m-d H:i:s')]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $locked = $pdo->prepare('SELECT id FROM dictionary_entries WHERE id = ? FOR UPDATE');
            $locked->execute([$id]);
            if ($locked->fetchColumn() === false) {
                throw new \RuntimeException('Entry does not exist.');
            }
            $statement = $pdo->prepare('UPDATE dictionary_entries SET word = ?, meaning = ?, example = ?, status = ?, updated_at = UTC_TIMESTAMP(), reviewed_at = ? WHERE id = ?');
            $statement->execute([$values['word'], $values['meaning'], $values['example'], $status, $status === 'pending' ? null : gmdate('Y-m-d H:i:s'), $id]);
            $pdo->prepare('DELETE FROM dictionary_entry_tags WHERE entry_id = ?')->execute([$id]);
        }
        $tagStatement = $pdo->prepare('INSERT INTO dictionary_entry_tags (entry_id, tag_id) VALUES (?, ?)');
        foreach ($values['tags'] as $tagId) {
            $tagStatement->execute([$id, $tagId]);
        }
        $pdo->commit();
        return $id;
    } catch (\Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function client_hash(): string
{
    $secret = app_config()['APP_SECRET'] ?? '';
    if (!is_string($secret) || strlen($secret) < 32) {
        throw new \RuntimeException('APP_SECRET must contain at least 32 characters.');
    }
    // Forwarded headers are deliberately ignored: they are spoofable without a trusted proxy configuration.
    return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), $secret);
}

/** Fixed-window limits are shared across sessions without storing client IP addresses. */
function rate_limit_allow(string $scope, int $limit, int $seconds, bool $consume = true): bool
{
    $pdo = db();
    $hash = client_hash();
    $bucket = intdiv(time(), $seconds) * $seconds;
    if ($consume) {
        $statement = $pdo->prepare('INSERT INTO dictionary_rate_limits (scope, client_hash, window_start, attempts) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1');
        $statement->execute([$scope, $hash, $bucket]);
    }
    $statement = $pdo->prepare('SELECT attempts FROM dictionary_rate_limits WHERE scope = ? AND client_hash = ? AND window_start = ?');
    $statement->execute([$scope, $hash, $bucket]);
    $attempts = (int) $statement->fetchColumn();
    return $consume ? $attempts <= $limit : $attempts < $limit;
}

function admin_configured(): bool
{
    $config = app_config();
    $plain = $config['ADMIN_PASSWORD'] ?? '';
    $hash = $config['ADMIN_PASSWORD_HASH'] ?? '';
    $credentialValid = is_string($plain) && $plain !== '';
    $credentialValid = $credentialValid || (is_string($hash) && (password_get_info($hash)['algoName'] ?? 'unknown') !== 'unknown');
    return is_string($config['ADMIN_USERNAME'] ?? null) && trim($config['ADMIN_USERNAME']) !== ''
        && $credentialValid && is_string($config['APP_SECRET'] ?? null) && strlen($config['APP_SECRET']) >= 32;
}

function admin_password_matches(string $password): bool
{
    $config = app_config();
    if (is_string($config['ADMIN_PASSWORD'] ?? null) && $config['ADMIN_PASSWORD'] !== '') {
        // Both digests have equal length, so comparison timing does not reveal password length.
        return hash_equals(hash_hmac('sha256', $config['ADMIN_PASSWORD'], $config['APP_SECRET']),
            hash_hmac('sha256', $password, $config['APP_SECRET']));
    }
    return is_string($config['ADMIN_PASSWORD_HASH'] ?? null) && $config['ADMIN_PASSWORD_HASH'] !== ''
        && password_verify($password, $config['ADMIN_PASSWORD_HASH']);
}

function admin_session_fingerprint(): string
{
    $config = app_config();
    $credential = is_string($config['ADMIN_PASSWORD'] ?? null) && $config['ADMIN_PASSWORD'] !== ''
        ? 'plain:' . $config['ADMIN_PASSWORD'] : 'hash:' . (string) ($config['ADMIN_PASSWORD_HASH'] ?? '');
    return hash_hmac('sha256', (string) ($config['ADMIN_USERNAME'] ?? '') . "\0" . $credential, $config['APP_SECRET']);
}

function admin_logged_in(): bool
{
    start_session();
    if (($_SESSION['admin'] ?? false) !== true) {
        return false;
    }
    $fingerprint = $_SESSION['admin_fingerprint'] ?? null;
    if (!is_string($fingerprint) || !hash_equals(admin_session_fingerprint(), $fingerprint)) {
        // Rotating credentials or APP_SECRET revokes previously authenticated sessions.
        unset($_SESSION['admin'], $_SESSION['admin_last_activity'], $_SESSION['admin_fingerprint'], $_SESSION['csrf']);
        session_regenerate_id(true);
        return false;
    }
    return true;
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}
