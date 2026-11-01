<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Pure configuration regressions. No database connection or real environment file is used.
$root = dirname(__DIR__);
require $root . '/lib/app.php';
$temporary = sys_get_temp_dir() . '/blynine-bootstrap-' . bin2hex(random_bytes(6));
mkdir($temporary, 0700);
$environment = $temporary . '/settings.env';
$checks = 0;
$check = static function (bool $condition, string $description) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    $checks++;
    echo 'PASS: ' . $description . "\n";
};
$run = static function (string $code) use ($root, $environment, $temporary): mixed {
    $preamble = 'putenv(' . var_export('BLYNINE_CONFIG=' . $environment, true) . ');'
        . 'ini_set("session.save_path",' . var_export($temporary, true) . ');'
        . 'require ' . var_export($root . '/lib/app.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $preamble . $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start configuration regression.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Configuration regression failed: ' . $errors);
    }
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
};
try {
    file_put_contents($environment, <<<'ENV'
# Literal values must not be evaluated or interpolated.
DB_NAME=unconnected_test
DB_USER=unconnected_test
DB_PASS='test-only password # = $HOME $(echo never)'
ADMIN_PASSWORD="test-only administrator # = password"
SENDER_GROUP_IDS='one, two, one, ,three'
SENDER_TRIGGER_AUTOMATION=false
LITERAL=" a # = $HOME ${HOME} `echo never` " # trailing comment
ESCAPED="a\"b\\c"
UNQUOTED=abc#def # this is a comment
ENV
    );
    $literal = \Blynine\load_dotenv_file($environment);
    $check($literal['LITERAL'] === ' a # = $HOME ${HOME} `echo never` ', 'quoted dotenv values preserve whitespace, symbols, and shell syntax literally');
    $check($literal['UNQUOTED'] === 'abc#def' && $literal['ESCAPED'] === 'a"b\\c', 'dotenv comments and quote escapes follow literal parsing rules');
    $code = '$c=Blynine\\app_config();echo json_encode(["username"=>$c["ADMIN_USERNAME"],"secret"=>$c["APP_SECRET"],"fingerprint"=>Blynine\\admin_session_fingerprint(),"groups"=>$c["SENDER_GROUP_IDS"],"automation"=>$c["SENDER_TRIGGER_AUTOMATION"],"correct"=>Blynine\\admin_password_matches("test-only administrator # = password"),"wrong"=>Blynine\\admin_password_matches("wrong"),"configured"=>Blynine\\admin_configured()]);';
    $first = $run($code);
    $second = $run($code);
    $check($first['username'] === 'admin' && $first['configured'] === true, 'dotenv needs no explicit username, hash, or secret to configure the admin');
    $check(preg_match('/^[a-f0-9]{64}$/D', $first['secret']) === 1 && $first['secret'] === $second['secret'] && $first['fingerprint'] === $second['fingerprint'], 'derived application secret and login fingerprint stay stable across requests');
    $check($first['correct'] === true && $first['wrong'] === false, 'plaintext environment password accepts only the correct login');
    $check($first['groups'] === ['one', 'two', 'three'] && $first['automation'] === false, 'dotenv Sender groups and booleans normalize to legacy types');
    $contents = file_get_contents($environment);
    file_put_contents($environment, str_replace('test-only administrator # = password', 'rotated administrator # = password', $contents));
    $rotated = $run($code);
    $check($rotated['secret'] !== $first['secret'] && $rotated['fingerprint'] !== $first['fingerprint'], 'changing the configured password rotates the derived secret and session fingerprint');
    file_put_contents($environment, "DB_PASS=\"unclosed\n");
    $rejected = false;
    try {
        \Blynine\load_dotenv_file($environment);
    } catch (RuntimeException $error) {
        $rejected = !str_contains($error->getMessage(), 'unclosed');
    }
    $check($rejected, 'malformed dotenv reports its line without echoing the value');
    file_put_contents($environment, "DB_PASS=\"value\" trailing\n");
    $rejected = false;
    try {
        \Blynine\load_dotenv_file($environment);
    } catch (RuntimeException $error) {
        $rejected = true;
    }
    $check($rejected, 'text after a quoted dotenv value is rejected');
    file_put_contents($environment, "DB_PASS=first\nDB_PASS=second\n");
    $rejected = false;
    try {
        \Blynine\load_dotenv_file($environment);
    } catch (RuntimeException $error) {
        $rejected = true;
    }
    $check($rejected, 'duplicate dotenv keys cannot silently override credentials');
    file_put_contents($environment, "DB_NAME=unconnected_test\nDB_USER=unconnected_test\nDB_PASS=x\nADMIN_PASSWORD=short\n");
    $weak = $run('try{Blynine\\app_config();echo json_encode(false);}catch(Throwable $e){echo json_encode(!str_contains($e->getMessage(),"short"));}');
    $check($weak === true, 'short administrator passwords fail closed without revealing their value');
    file_put_contents($environment, "DB_NAME=unconnected_test\nDB_USER=unconnected_test\nDB_PASS=x\n");
    $noSecret = $run('try{Blynine\\app_config();echo json_encode(false);}catch(Throwable $e){echo json_encode(true);}');
    $check($noSecret === true, 'missing private entropy cannot produce a shared default secret');
    echo $checks . " configuration checks passed.\n";
} finally {
    foreach (glob($temporary . '/*') as $path) {
        unlink($path);
    }
    rmdir($temporary);
}
