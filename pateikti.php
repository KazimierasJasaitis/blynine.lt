<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    header('Location: /?pateikta=1');
    exit;
}

$suggestion = trim((string) ($_POST['pasiulymas'] ?? ''));
$email = trim((string) ($_POST['elpastas'] ?? ''));
$news = isset($_POST['naujienos']);

if ($suggestion === '' || mb_strlen($suggestion) > 2000 || mb_strlen($email) > 200) {
    header('Location: /');
    exit;
}

if ($news && $email === '') {
    header('Location: /');
    exit;
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    header('Location: /');
    exit;
}

$configPath = __DIR__ . '/env.local.php';
$config = is_file($configPath) ? require $configPath : null;
if (!is_array($config)) {
    error_log('blynine: env.local.php is missing.');
    header('Location: /');
    exit;
}

try {
    $pdo = blynine_db($config);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS pateikimai (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            suggestion TEXT NOT NULL,
            email VARCHAR(200) NOT NULL DEFAULT \'\',
            news TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $stmt = $pdo->prepare(
        'INSERT INTO pateikimai (created_at, suggestion, email, news) VALUES (UTC_TIMESTAMP(), ?, ?, ?)'
    );
    $stmt->execute([$suggestion, $email, $news ? 1 : 0]);
} catch (Throwable $error) {
    error_log('blynine: suggestion was not saved.');
    header('Location: /');
    exit;
}

if ($news) {
    require_once __DIR__ . '/lib/sender.php';
    \Blynine\Sender\subscribe($email);
}

header('Location: /?pateikta=1');
exit;

function blynine_db(array $config): PDO
{
    $host = $config['DB_HOST'] ?? '';
    $name = $config['DB_NAME'] ?? '';
    $user = $config['DB_USER'] ?? '';
    $pass = $config['DB_PASS'] ?? '';
    $port = $config['DB_PORT'] ?? 3306;

    if (!is_string($host) || !preg_match('/^[A-Za-z0-9._-]{1,128}$/', $host)) {
        throw new RuntimeException('db host');
    }
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
        throw new RuntimeException('db name');
    }
    if (!is_string($user) || $user === '' || !preg_match('/^[A-Za-z0-9_@.-]{1,64}$/', $user)) {
        throw new RuntimeException('db user');
    }
    if (!is_string($pass)) {
        throw new RuntimeException('db pass');
    }
    if (is_string($port) && ctype_digit($port)) {
        $port = (int) $port;
    }
    if (!is_int($port) || $port < 1 || $port > 65535) {
        throw new RuntimeException('db port');
    }

    return new PDO(
        'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
