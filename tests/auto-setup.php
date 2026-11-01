<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
// This destructive reset is restricted to the named, disposable local test database.
$config = require __DIR__ . '/config.php';
if ($config['DB_NAME'] !== 'blynine_test' || $config['DB_USER'] !== 'blynine_test') {
    throw new RuntimeException('Automatic setup tests require the isolated test database.');
}
$pdo = new PDO('mysql:host=' . $config['DB_HOST'] . ';port=' . $config['DB_PORT'] . ';dbname=blynine_test;charset=utf8mb4',
    $config['DB_USER'], $config['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$base = rtrim(getenv('BLYNINE_TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
if (!preg_match('~^http://(?:127\.0\.0\.1|localhost)(?::\d+)?$~D', $base)) {
    throw new RuntimeException('Automatic setup checks only target a local server.');
}
$tables = ['dictionary_entry_tags', 'dictionary_entries', 'dictionary_tags', 'dictionary_rate_limits', 'schema_migrations', 'blynine_metadata'];
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($tables as $table) $pdo->exec('DROP TABLE IF EXISTS ' . $table);
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$check = static function (bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    echo 'PASS: ' . $description . "\n";
};
$multi = curl_multi_init();
$requests = [];
for ($i = 0; $i < 8; ++$i) {
    $request = curl_init($base . '/keiksmazodziai/');
    curl_setopt_array($request, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $requests[] = $request;
    curl_multi_add_handle($multi, $request);
}
do {
    $status = curl_multi_exec($multi, $running);
    if ($running) curl_multi_select($multi, 1);
} while ($running && $status === CURLM_OK);
foreach ($requests as $index => $request) {
    $body = curl_multi_getcontent($request);
    $check(curl_getinfo($request, CURLINFO_RESPONSE_CODE) === 200 && str_contains($body, 'Kartais tiesiog'), 'concurrent first visit ' . ($index + 1) . ' initializes successfully');
    curl_multi_remove_handle($multi, $request);
}
curl_multi_close($multi);
$check((int) $pdo->query('SELECT COUNT(*) FROM dictionary_entries')->fetchColumn() === 30, 'empty database receives exactly 30 entries without CLI setup');
$check((int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 1, 'concurrent visits apply the initial migration once');
$check((int) $pdo->query("SELECT COUNT(*) FROM blynine_metadata WHERE meta_key='bootstrap_fingerprint'")->fetchColumn() === 1, 'setup records a completion fingerprint');
$entry = $pdo->query("SELECT id, meaning, status FROM dictionary_entries WHERE seed_key='v1-velnias'")->fetch(PDO::FETCH_ASSOC);
$tag = $pdo->query("SELECT id, label FROM dictionary_tags WHERE slug='angliski'")->fetch(PDO::FETCH_ASSOC);
try {
    $pdo->prepare("UPDATE dictionary_entries SET meaning='TEST edited meaning',status='rejected' WHERE id=?")->execute([$entry['id']]);
    $pdo->prepare("UPDATE dictionary_tags SET label='TEST renamed label' WHERE id=?")->execute([$tag['id']]);
    $pdo->exec("DELETE FROM blynine_metadata WHERE meta_key='bootstrap_fingerprint'");
    $request = curl_init($base . '/keiksmazodziai/');
    curl_setopt_array($request, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    curl_exec($request);
    $check(curl_getinfo($request, CURLINFO_RESPONSE_CODE) === 200, 'an interrupted or outdated setup retries automatically');
    $updated = $pdo->query("SELECT meaning,status FROM dictionary_entries WHERE seed_key='v1-velnias'")->fetch(PDO::FETCH_ASSOC);
    $check($updated['meaning'] === 'TEST edited meaning' && $updated['status'] === 'rejected', 'automatic upgrades preserve edited and unpublished starter entries');
    $label = $pdo->query("SELECT label FROM dictionary_tags WHERE slug='angliski'")->fetchColumn();
    $check($label === 'TEST renamed label', 'automatic upgrades preserve custom tag labels');
    $check((int) $pdo->query('SELECT COUNT(*) FROM dictionary_entries')->fetchColumn() === 30, 'repeated setup never duplicates the starter vocabulary');
} finally {
    $pdo->prepare('UPDATE dictionary_entries SET meaning=?,status=? WHERE id=?')->execute([$entry['meaning'], $entry['status'], $entry['id']]);
    $pdo->prepare('UPDATE dictionary_tags SET label=? WHERE id=?')->execute([$tag['label'], $tag['id']]);
}
