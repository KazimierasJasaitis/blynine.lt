<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
$temporary = sys_get_temp_dir() . '/blynine-regression-' . bin2hex(random_bytes(6));
mkdir($temporary, 0700);
$fixture = require __DIR__ . '/config.php';
$configFile = $temporary . '/config.php';
$writeConfig = static function (array $config) use ($configFile): void {
    file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
};
$writeConfig($fixture);
$run = static function (string $code, bool $bootstrap = true) use ($root, $temporary, $configFile): array {
    $preamble = 'putenv(' . var_export('BLYNINE_CONFIG=' . $configFile, true) . ');'
        . 'ini_set("session.save_path", ' . var_export($temporary, true) . ');'
        . '$_SERVER["REQUEST_METHOD"]="GET";';
    if ($bootstrap) $preamble .= 'require ' . var_export($root . '/lib/app.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $preamble . $code], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start isolated PHP regression.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException('PHP regression failed: ' . $errors);
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
};
$check = static function (bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
    echo 'PASS: ' . $description . "\n";
};
$renderDictionary = 'ob_start();require ' . var_export($root . '/lib/dictionary.php', true)
    . ';Blynine\\render_dictionary();$html=ob_get_clean();echo json_encode(["status"=>http_response_code(),"body"=>$html]);';
try {
    unlink($configFile);
    $result = $run($renderDictionary);
    $check($result['status'] === 503 && str_contains($result['body'], 'Žodynas trumpam užstrigo.'), 'missing configuration returns a readable 503');
    $failedDb = $fixture;
    $failedDb['DB_HOST'] = '127.0.0.1'; $failedDb['DB_PORT'] = 1;
    $writeConfig($failedDb);
    $result = $run($renderDictionary);
    $check($result['status'] === 503 && !str_contains($result['body'], 'SQLSTATE'), 'database connection failures show no internal error details');
    $missingAdmin = $fixture;
    $missingAdmin['ADMIN_PASSWORD_HASH'] = '';
    $writeConfig($missingAdmin);
    $result = $run('ob_start();require ' . var_export($root . '/admin/index.php', true) . ';$html=ob_get_clean();echo json_encode(["status"=>http_response_code(),"body"=>$html]);', false);
    $check($result['status'] === 503 && !str_contains($result['body'], 'id="password"'), 'unconfigured administrator login fails closed');
    $writeConfig($fixture);
    $create = $run('Blynine\\start_session();$_SESSION["admin"]=true;$_SESSION["admin_last_activity"]=time();$_SESSION["admin_fingerprint"]=Blynine\\admin_session_fingerprint();$_SESSION["csrf"]="old-token";echo json_encode(["id"=>session_id()]);');
    $sessionId = var_export($create['id'], true);
    $result = $run('session_id(' . $sessionId . ');echo json_encode(["logged_in"=>Blynine\\admin_logged_in()]);');
    $check($result['logged_in'] === true, 'unchanged credentials preserve an authenticated session');
    $rotated = $fixture; $rotated['APP_SECRET'] .= '-rotated'; $writeConfig($rotated);
    $result = $run('session_id(' . $sessionId . ');$loggedIn=Blynine\\admin_logged_in();echo json_encode(["logged_in"=>$loggedIn,"csrf"=>isset($_SESSION["csrf"])]);');
    $check($result['logged_in'] === false && $result['csrf'] === false, 'credential rotation revokes authentication and the old CSRF token');
    $writeConfig($fixture);
    $create = $run('Blynine\\start_session();$_SESSION["admin"]=true;$_SESSION["admin_last_activity"]=time()-1801;$_SESSION["admin_fingerprint"]=Blynine\\admin_session_fingerprint();echo json_encode(["id"=>session_id()]);');
    $result = $run('session_id(' . var_export($create['id'], true) . ');echo json_encode(["logged_in"=>Blynine\\admin_logged_in()]);');
    $check($result['logged_in'] === false, 'idle administrator sessions expire after 30 minutes');
} finally {
    foreach (glob($temporary . '/*') as $path) unlink($path);
    rmdir($temporary);
}
