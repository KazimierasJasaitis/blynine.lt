<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib/app.php';

use function Blynine\{entry_input, flash, post_string, rate_limit_allow, redirect, save_entry, start_session, verify_csrf};

// Keep bounded scalar input even when validation cannot reach the database.
$old = [];
foreach (['word' => 200, 'meaning' => 1500, 'example' => 800] as $field => $maximum) {
    $old[$field] = mb_substr(post_string($field), 0, $maximum);
}
$rawTags = is_array($_POST['tags'] ?? null) ? array_slice($_POST['tags'], 0, 12) : [];
$old['tags'] = [];
foreach ($rawTags as $tag) {
    if ((is_int($tag) || is_string($tag)) && ctype_digit((string) $tag)) {
        $old['tags'][] = (int) $tag;
    }
}
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Leidžiamas tik pateikimas iš formos.');
}

try {
    start_session();
    if (!verify_csrf(post_string('csrf'))) {
        flash('error', 'Forma paseno. Atnaujink puslapį ir pabandyk dar kartą.', $old);
        redirect('/keiksmazodziai/#pasiulyti');
    }
    if (post_string('website') !== '') {
        flash('success', 'Ačiū! Pasiūlymas pateiktas peržiūrai.');
        redirect('/keiksmazodziai/#pasiulyti');
    }
    [$values, $errors] = entry_input($_POST);
    if ($errors !== []) {
        flash('error', 'Patikrink pažymėtus laukus.', $old, $errors);
        redirect('/keiksmazodziai/#pasiulyti');
    }
    if (!rate_limit_allow('submission', 5, 3600)) {
        flash('error', 'Per valandą galima pateikti 5 pasiūlymus. Pabandyk vėliau.', $values);
        redirect('/keiksmazodziai/#pasiulyti');
    }
    save_entry(null, $values, 'pending');
    flash('success', 'Ačiū! Keiksmažodis pateiktas peržiūrai. Jis pasirodys, kai administratorius jį patvirtins.');
} catch (Throwable $error) {
    error_log('blynine submission: ' . get_class($error));
    flash('error', 'Šiuo metu pasiūlymo išsaugoti nepavyko. Pabandyk vėliau.', $old);
}
redirect('/keiksmazodziai/#pasiulyti');
