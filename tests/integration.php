<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
putenv('BLYNINE_CONFIG=' . __DIR__ . '/config.php');
require (getenv('BLYNINE_TEST_APP_ROOT') ?: dirname(__DIR__)) . '/lib/app.php';

$config = \Blynine\app_config();
if ($config['DB_NAME'] !== 'blynine_test' || $config['DB_USER'] !== 'blynine_test') {
    throw new RuntimeException('Integration tests require the isolated test database.');
}
$pdo = \Blynine\db();
$base = rtrim(getenv('BLYNINE_TEST_BASE_URL') ?: 'http://127.0.0.1', '/');
if (!preg_match('~^http://(?:127\.0\.0\.1|localhost)(?::\d+)?$~D', $base)) {
    throw new RuntimeException('Integration tests only target a local HTTP server.');
}
$cookie = tempnam(sys_get_temp_dir(), 'blynine-test-');
$checks = 0;
function check(bool $condition, string $description): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    ++$checks;
    echo 'PASS: ' . $description . "\n";
}
function request(string $path, ?array $data = null, bool $cookies = true): array
{
    global $base, $cookie;
    $handle = curl_init($base . $path);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15];
    if ($cookies) {
        $options[CURLOPT_COOKIEJAR] = $cookie;
        $options[CURLOPT_COOKIEFILE] = $cookie;
    }
    if ($data !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($data);
    }
    curl_setopt_array($handle, $options);
    $response = curl_exec($handle);
    if ($response === false) throw new RuntimeException(curl_error($handle));
    $size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers' => substr($response, 0, $size), 'body' => substr($response, $size)];
}
function csrf(array $response): string
{
    if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $response['body'], $match)) {
        throw new RuntimeException('Missing CSRF token.');
    }
    return $match[1];
}
function state(int $id): string
{
    global $pdo;
    $statement = $pdo->prepare('SELECT status FROM dictionary_entries WHERE id = ?');
    $statement->execute([$id]);
    return (string) $statement->fetchColumn();
}
$prefix = 'ZZ-TEST-' . bin2hex(random_bytes(4));
$tagId = null;
$ids = [];
try {
    $pdo->exec('DELETE FROM dictionary_rate_limits');
    $public = request('/keiksmazodziai/');
    check($public['status'] === 200 && str_contains($public['body'], 'Kartais tiesiog'), 'dictionary loads from the isolated MySQL database');
    check(str_contains($public['body'], 'English') && str_contains($public['body'], 'fuck ruzia'), 'requested English tag labels stay intact');
    check(str_contains(strtolower($public['headers']), 'no-store'), 'dictionary responses cannot be cached');
    check((int) $pdo->query("SELECT COUNT(*) FROM dictionary_entries WHERE seed_key IS NOT NULL")->fetchColumn() === 30, '30 seed entries are present');
    check(request('/keiksmazodziai/?q=%25')['status'] === 200 && \Blynine\dictionary_data('%')['total'] === 0, 'search wildcard characters are treated literally');
    $englishTag = array_values(array_filter(\Blynine\all_tags(), fn (array $tag) => $tag['label'] === 'English'))[0];
    check(\Blynine\dictionary_data('', $englishTag['slug'])['total'] > 0, 'tag filtering returns matching approved entries');
    $pageTwo = request('/keiksmazodziai/?page=2');
    check(str_contains($pageTwo['body'], '>19</span>'), 'pagination continues numbering at entry 19');
    check(request('/keiksmazodziai/?q[]=x&page[]=x')['status'] === 200, 'malformed query arrays do not crash the page');
    check(request('/keiksmazodziai/pateikti.php')['status'] === 405, 'submissions require POST');
    $token = csrf($public);
    request('/keiksmazodziai/pateikti.php', ['csrf' => $token, 'word' => '', 'meaning' => '']);
    $invalid = request('/keiksmazodziai/');
    check(str_contains($invalid['body'], 'aria-invalid="true"'), 'invalid submission fields receive accessible errors');
    $word = $prefix . '-<script>alert(1)</script>';
    $form = ['csrf' => $token, 'word' => $word, 'meaning' => 'Bandymo reikšmė', 'example' => 'Bandymo sakinys', 'tags' => [1], 'status' => 'approved'];
    $submitted = request('/keiksmazodziai/pateikti.php', $form);
    check($submitted['status'] === 303, 'valid anonymous submission redirects after saving');
    $lookup = $pdo->prepare('SELECT id, status FROM dictionary_entries WHERE word = ?');
    $lookup->execute([$word]);
    $row = $lookup->fetch();
    $id = (int) ($row['id'] ?? 0);
    $ids[] = $id;
    check($id > 0 && $row['status'] === 'pending', 'public submission cannot forge an approved status');
    check(\Blynine\dictionary_data($prefix)['total'] === 0, 'pending entries remain private');
    $confirmation = request('/keiksmazodziai/');
    check(str_contains($confirmation['body'], 'pateiktas peržiūrai'), 'submission confirmation is shown');
    request('/admin/', ['csrf' => $token, 'action' => 'status', 'id' => $id, 'expected_status' => 'pending', 'target_status' => 'approved']);
    check(state($id) === 'pending', 'unauthenticated moderation is rejected');
    $login = request('/admin/');
    $loginToken = csrf($login);
    request('/admin/', ['csrf' => $loginToken, 'action' => 'login', 'username' => 'test-admin', 'password' => 'wrong-password']);
    check(str_contains(request('/admin/')['body'], 'Neteisingas prisijungimo'), 'incorrect administrator credentials are rejected');
    request('/admin/', ['csrf' => $loginToken, 'action' => 'login', 'username' => 'test-admin', 'password' => 'test-password-only']);
    $admin = request('/admin/');
    check(str_contains($admin['body'], 'Atsijungti'), 'administrator can sign in');
    $adminToken = csrf($admin);
    check($adminToken !== $loginToken, 'login rotates the CSRF token');
    $sessionHeaders = strtolower($public['headers']);
    check(str_contains($sessionHeaders, 'httponly') && str_contains($sessionHeaders, 'samesite=lax'), 'session cookies protect browser access');
    request('/admin/', ['csrf' => 'wrong', 'action' => 'status', 'id' => $id, 'expected_status' => 'pending', 'target_status' => 'approved']);
    check(state($id) === 'pending', 'invalid CSRF tokens cannot approve entries');
    $moderation = ['csrf' => $adminToken, 'action' => 'status', 'id' => $id, 'expected_status' => 'pending', 'target_status' => 'approved'];
    request('/admin/', $moderation);
    check(state($id) === 'approved' && \Blynine\dictionary_data($prefix)['total'] === 1, 'approval publishes the entry');
    $escaped = request('/keiksmazodziai/?q=' . rawurlencode($prefix));
    check(str_contains($escaped['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($escaped['body'], '<script>alert(1)</script>'), 'user content is escaped in public output');
    request('/admin/', array_replace($moderation, ['target_status' => 'rejected']));
    check(state($id) === 'approved', 'stale duplicate moderation does not reverse a review');
    $renamed = $prefix . '-edited';
    request('/admin/', ['csrf' => $adminToken, 'action' => 'save', 'id' => $id, 'word' => $renamed, 'meaning' => 'Redaguota reikšmė', 'example' => '', 'tags' => [1], 'status' => 'approved']);
    check(\Blynine\dictionary_data($renamed)['total'] === 1, 'administrator edits are published');
    request('/admin/', array_replace($moderation, ['expected_status' => 'approved', 'target_status' => 'pending']));
    check(\Blynine\dictionary_data($prefix)['total'] === 0, 'unpublishing removes the public entry');
    request('/admin/', array_replace($moderation, ['expected_status' => 'pending', 'target_status' => 'rejected']));
    check(state($id) === 'rejected', 'administrator can reject a submission');
    request('/admin/', ['csrf' => $adminToken, 'action' => 'tag_add', 'label' => $prefix . '-tag']);
    $tagLookup = $pdo->prepare('SELECT id FROM dictionary_tags WHERE label = ?');
    $tagLookup->execute([$prefix . '-tag']);
    $tagId = (int) $tagLookup->fetchColumn();
    check($tagId > 0, 'administrator can create a custom tag');
    request('/admin/', ['csrf' => $adminToken, 'action' => 'save', 'id' => '', 'word' => $prefix . '-created', 'meaning' => 'Naujas įrašas', 'example' => '', 'tags' => [$tagId], 'status' => 'approved']);
    $new = \Blynine\dictionary_data($prefix . '-created');
    $createdId = (int) ($new['entries'][0]['id'] ?? 0);
    $ids[] = $createdId;
    check($createdId > 0, 'administrator can create a published entry directly');
    request('/admin/', ['csrf' => $adminToken, 'action' => 'tag_rename', 'id' => $tagId, 'label' => $prefix . '-renamed']);
    $new = \Blynine\dictionary_data($prefix . '-created');
    check(($new['entries'][0]['tags'][0]['label'] ?? '') === $prefix . '-renamed', 'renaming a tag preserves entry associations');
    request('/admin/', ['csrf' => $adminToken, 'action' => 'logout']);
    check(!str_contains(request('/admin/')['body'], 'Atsijungti'), 'logout revokes administrator access');
    $pdo->exec('DELETE FROM dictionary_rate_limits');
    $public = request('/keiksmazodziai/');
    $token = csrf($public);
    for ($i = 0; $i < 6; ++$i) {
        request('/keiksmazodziai/pateikti.php', ['csrf' => $token, 'word' => $prefix . '-rate-' . $i, 'meaning' => 'Bandymas']);
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM dictionary_entries WHERE word LIKE ?');
    $count->execute([$prefix . '-rate-%']);
    check((int) $count->fetchColumn() === 5, 'submission throttling persists across requests');
    $pdo->exec('DELETE FROM dictionary_rate_limits');
    $loginToken = csrf(request('/admin/'));
    for ($i = 0; $i < 5; ++$i) request('/admin/', ['csrf' => $loginToken, 'action' => 'login', 'username' => 'test-admin', 'password' => 'wrong']);
    request('/admin/', ['csrf' => $loginToken, 'action' => 'login', 'username' => 'test-admin', 'password' => 'test-password-only']);
    check(str_contains(request('/admin/')['body'], 'Per daug nesėkmingų'), 'login throttling blocks further attempts after five failures');
    foreach (['/.env', '/.env.example', '/.env.backup', '/env.local.php', '/env.example.php', '/.git/config', '/lib/app.php', '/tests/config.php', '/scripts/migrate.php', '/migrations/001_dictionary.sql', '/game/package.json'] as $path) {
        check(request($path)['status'] === 403, 'Apache denies access to ' . $path);
    }
    $root = request('/');
    check(in_array($root['status'], [200, 302], true) && str_contains(strtolower($root['headers']), 'no-store'), 'root draw is non-cacheable');
    if ($root['status'] === 302) {
        check(str_contains($root['headers'], 'Location: https://pirmasblynas.lt/'), 'external selection uses the fixed destination');
    } else {
        check(str_contains($root['body'], 'Kartais tiesiog') || str_contains($root['body'], 'id="game"'), 'root renders a registered local experience');
    }
    check(request('/index.html')['status'] === 302, 'old placeholder URL redirects to the root draw');
    check(request('/definitely-missing-page')['status'] === 404, 'unknown routes return the designed 404 page');
    $game = request('/zaidimas/');
    check($game['status'] === 200 && str_contains($game['body'], 'Žaisti'), 'built Lithuanian game is served directly');
    preg_match_all('~(?:src|href)="(/zaidimas/[^"?#]+)~', $game['body'], $assetMatches);
    check(count($assetMatches[1]) > 0, 'game uses its deployment asset base');
    foreach (array_unique($assetMatches[1]) as $asset) check(request($asset)['status'] === 200, 'game asset loads: ' . $asset);
    echo "\n{$checks} integration checks passed.\n";
} finally {
    $cleanup = $pdo->prepare('DELETE FROM dictionary_entries WHERE word LIKE ?');
    $cleanup->execute([$prefix . '%']);
    if ($tagId) $pdo->prepare('DELETE FROM dictionary_tags WHERE id = ?')->execute([$tagId]);
    $pdo->exec('DELETE FROM dictionary_rate_limits');
    if (is_file($cookie)) unlink($cookie);
}
