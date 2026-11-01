<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/experiences.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$experience = \Blynine\choose_experience();
if ($experience['type'] === 'redirect') {
    header('Location: ' . $experience['url'], true, 302);
    exit;
}
if ($experience['type'] === 'dictionary') {
    require_once __DIR__ . '/lib/dictionary.php';
    \Blynine\render_dictionary();
    exit;
}
?><!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#17191c">
  <meta name="description" content="Blyninė. Kiekvieną kartą – kita istorija. Šįkart vartom blynus.">
  <title>Blyninė — dar vieną blyną</title>
  <link rel="icon" href="/assets/mark.svg" type="image/svg+xml">
  <style>html,body{margin:0;width:100%;height:100%;overflow:hidden;background:#17191c}iframe{position:fixed;inset:0;width:100%;height:100%;border:0;display:block}</style>
</head>
<body>
  <iframe id="game" src="/zaidimas/" title="Blynų vartymo žaidimas" allow="fullscreen" allowfullscreen></iframe>
  <script>
    document.getElementById('game').addEventListener('load', function () { this.contentWindow.focus(); });
    window.addEventListener('pageshow', function (event) { if (event.persisted) window.location.reload(); });
  </script>
</body>
</html>
