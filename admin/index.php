<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib/app.php';

use function Blynine\{admin_configured, admin_logged_in, admin_password_matches, admin_session_fingerprint, all_tags, app_config, csrf_token, db, e, entry_input, flash, post_string, rate_limit_allow, redirect, save_entry, start_session, take_flash, verify_csrf};

header('Cache-Control: no-store, private');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$setupError = '';
$available = false;
try {
    start_session();
    $available = admin_configured();
    if (!$available) {
        $setupError = 'Administratoriaus prisijungimas dar nesukonfigūruotas. Į .env failą įrašyk ADMIN_USERNAME ir bent 12 ženklų ADMIN_PASSWORD.';
    }
} catch (Throwable $error) {
    error_log('blynine admin configuration: ' . get_class($error));
    $setupError = 'Administratoriaus skiltis šiuo metu nepasiekiama. Patikrink serverio konfigūraciją.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $available) {
    try {
        if (!verify_csrf(post_string('csrf'))) {
            flash('error', 'Forma paseno. Atnaujink puslapį ir pabandyk dar kartą.');
            redirect('/admin/');
        }
        $action = post_string('action');
        if ($action === 'login') {
            if (admin_logged_in()) {
                redirect('/admin/');
            }
            if (!rate_limit_allow('login', 5, 900, false)) {
                flash('error', 'Per daug nesėkmingų bandymų. Pabandyk po 15 minučių.');
                redirect('/admin/');
            }
            $config = app_config();
            $username = post_string('username');
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $correctPassword = admin_password_matches($password);
            if (!hash_equals($config['ADMIN_USERNAME'], $username) || !$correctPassword) {
                rate_limit_allow('login', 5, 900);
                flash('error', 'Neteisingas prisijungimo vardas arba slaptažodis.');
                redirect('/admin/');
            }
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            $_SESSION['admin'] = true;
            $_SESSION['admin_fingerprint'] = admin_session_fingerprint();
            $_SESSION['admin_last_activity'] = time();
            flash('success', 'Prisijungei.');
            redirect('/admin/');
        }
        if (!admin_logged_in()) {
            flash('error', 'Norint atlikti šį veiksmą, reikia prisijungti.');
            redirect('/admin/');
        }
        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            flash('success', 'Atsijungei.');
            redirect('/admin/');
        }
        if ($action === 'save') {
            [$values, $errors] = entry_input($_POST);
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $rawId = $_POST['id'] ?? '';
            $isNew = is_string($rawId) && trim($rawId) === '';
            $status = post_string('status');
            if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $errors['status'] = 'Pasirink būseną iš sąrašo.';
            }
            if (!$isNew && $id === false) {
                flash('error', 'Neteisingas įrašo numeris.');
                redirect('/admin/');
            }
            if ($errors !== []) {
                flash('error', 'Patikrink pažymėtus laukus.', $values + ['id' => $isNew ? '' : $id, 'status' => $status], $errors);
                redirect($isNew ? '/admin/?new=1' : '/admin/?edit=' . $id);
            }
            save_entry($isNew ? null : $id, $values, $status);
            flash('success', $isNew ? 'Įrašas sukurtas.' : 'Pakeitimai išsaugoti.');
            redirect('/admin/?status=' . rawurlencode($status));
        }
        if ($action === 'status') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $expected = post_string('expected_status');
            $target = post_string('target_status');
            if ($id === false || !in_array($expected, ['pending', 'approved', 'rejected'], true)
                || !in_array($target, ['pending', 'approved', 'rejected'], true)) {
                flash('error', 'Neteisingas veiksmas.');
                redirect('/admin/');
            }
            $statement = db()->prepare('UPDATE dictionary_entries SET status = ?, updated_at = UTC_TIMESTAMP(), reviewed_at = ? WHERE id = ? AND status = ?');
            $statement->execute([$target, $target === 'pending' ? null : gmdate('Y-m-d H:i:s'), $id, $expected]);
            flash($statement->rowCount() > 0 ? 'success' : 'error', $statement->rowCount() > 0 ? 'Įrašo būsena pakeista.' : 'Įrašas jau pasikeitė. Peržiūrėk atnaujintą sąrašą.');
            redirect('/admin/?status=' . rawurlencode($expected));
        }
        if ($action === 'tag_add' || $action === 'tag_rename') {
            $label = post_string('label');
            if ($label === '' || mb_strlen($label) > 80 || preg_match('/[\x00-\x1F\x7F]/u', $label)) {
                flash('error', 'Žymos pavadinimas turi būti nuo 1 iki 80 ženklų.');
                redirect('/admin/?tags=1');
            }
            if ($action === 'tag_add') {
                // Slugs remain stable when labels are renamed and work with every alphabet.
                $slug = 'tag-' . bin2hex(random_bytes(8));
                $statement = db()->prepare('INSERT INTO dictionary_tags (slug, label) VALUES (?, ?)');
                $statement->execute([$slug, $label]);
            } else {
                $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) {
                    flash('error', 'Neteisingas žymos numeris.');
                    redirect('/admin/?tags=1');
                }
                $statement = db()->prepare('UPDATE dictionary_tags SET label = ? WHERE id = ?');
                $statement->execute([$label, $id]);
            }
            flash('success', 'Žyma išsaugota.');
            redirect('/admin/?tags=1');
        }
        flash('error', 'Nežinomas veiksmas.');
    } catch (PDOException $error) {
        error_log('blynine admin database: ' . $error->getCode());
        flash('error', $error->getCode() === '23000' ? 'Tokia žyma jau yra. Pasirink kitą pavadinimą.' : 'Nepavyko išsaugoti. Patikrink duomenų bazę ir pabandyk dar kartą.');
    } catch (Throwable $error) {
        error_log('blynine admin action: ' . get_class($error));
        flash('error', 'Veiksmo atlikti nepavyko. Pabandyk dar kartą.');
    }
    redirect('/admin/');
}

$loggedIn = $available && admin_logged_in();
$notice = take_flash();
$csrf = csrf_token();
$tags = [];
$rows = [];
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$status = is_string($_GET['status'] ?? null) && in_array($_GET['status'], array_keys($counts), true) ? $_GET['status'] : 'pending';
$viewTags = isset($_GET['tags']);
$edit = null;
$page = max(1, (int) (is_scalar($_GET['page'] ?? null) ? $_GET['page'] : 1));
$pages = 1;
if ($loggedIn) {
    try {
        $pdo = db();
        $tags = all_tags();
        foreach ($pdo->query('SELECT status, COUNT(*) AS count FROM dictionary_entries GROUP BY status')->fetchAll() as $count) {
            $counts[$count['status']] = (int) $count['count'];
        }
        if (isset($_GET['new'])) {
            $edit = ['id' => '', 'word' => '', 'meaning' => '', 'example' => '', 'status' => 'approved', 'tags' => []];
        } elseif (isset($_GET['edit'])) {
            $id = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $statement = $pdo->prepare('SELECT id, word, meaning, example, status FROM dictionary_entries WHERE id = ?');
            $statement->execute([$id === false ? 0 : $id]);
            $edit = $statement->fetch() ?: null;
            if ($edit !== null) {
                $statement = $pdo->prepare('SELECT tag_id FROM dictionary_entry_tags WHERE entry_id = ?');
                $statement->execute([$edit['id']]);
                $edit['tags'] = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            } else {
                $notice = ['kind' => 'error', 'message' => 'Įrašas nerastas.', 'old' => [], 'errors' => []];
            }
        }
        if ($edit !== null && ($notice['old'] ?? []) !== []) {
            $edit = array_replace($edit, $notice['old']);
        }
        $pages = max(1, (int) ceil($counts[$status] / 25));
        $page = min($pages, $page);
        if (!$viewTags && $edit === null) {
            $statement = $pdo->prepare('SELECT id, word, meaning, example, status, created_at FROM dictionary_entries WHERE status = ? ORDER BY created_at DESC, id DESC LIMIT 25 OFFSET ' . (($page - 1) * 25));
            $statement->execute([$status]);
            $rows = $statement->fetchAll();
        }
    } catch (Throwable $error) {
        error_log('blynine admin read: ' . get_class($error));
        $setupError = 'Nepavyko pasiekti žodyno duomenų. Patikrink duomenų bazę ir paleisk migracijas.';
        http_response_code(503);
    }
} elseif (!$available) {
    http_response_code(503);
}
$statusLabels = ['pending' => 'Laukia patvirtinimo', 'approved' => 'Paskelbti', 'rejected' => 'Atmesti'];
$entryCount = $counts[$status];
$entryNoun = match (true) {
    $entryCount % 10 === 1 && $entryCount % 100 !== 11 => 'įrašas',
    $entryCount % 10 >= 2 && $entryCount % 10 <= 9 && ($entryCount % 100 < 11 || $entryCount % 100 > 19) => 'įrašai',
    default => 'įrašų',
};
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Administravimas · blyninė</title>
  <link rel="icon" href="/assets/mark.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/site.css">
  <link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="admin-body">
<main class="container admin-shell">
  <header class="admin-header">
    <a class="brand" href="/keiksmazodziai/"><img class="brand-mark" src="/assets/mark.svg" alt=""> blyninė<span class="admin-label">administravimas</span></a>
    <?php if ($loggedIn): ?><form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="button button-secondary button-small" type="submit">Atsijungti</button></form><?php endif; ?>
  </header>
  <?php if ($notice !== null): ?><div class="notice notice-<?= e($notice['kind']) ?>" role="status"><?= e($notice['message']) ?></div><?php endif; ?>
  <?php if ($setupError !== ''): ?><div class="notice notice-error" role="alert"><?= e($setupError) ?></div><?php endif; ?>
  <?php if (!$loggedIn && $available): ?>
  <section class="admin-login admin-panel">
    <p class="eyebrow">Tik administratoriui</p><h1>Prisijungimas</h1>
    <form method="post" class="admin-form">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="login">
      <div class="field"><label for="username">Prisijungimo vardas</label><input id="username" name="username" autocomplete="username" required maxlength="80"></div>
      <div class="field"><label for="password">Slaptažodis</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
      <button class="button" type="submit">Prisijungti</button>
    </form>
  </section>
  <?php elseif ($loggedIn && $setupError === ''): ?>
  <nav class="admin-tabs" aria-label="Administravimas">
    <?php foreach ($statusLabels as $key => $label): ?><a class="<?= !$viewTags && $edit === null && $status === $key ? 'is-active' : '' ?>" href="/admin/?status=<?= e($key) ?>"><?= e($label) ?> <span><?= $counts[$key] ?></span></a><?php endforeach; ?>
    <a class="<?= $viewTags ? 'is-active' : '' ?>" href="/admin/?tags=1">Žymos</a>
    <a href="/admin/?new=1">+ Naujas įrašas</a>
  </nav>
  <?php if ($viewTags): ?>
    <section class="admin-panel"><p class="eyebrow">Tvarka žodyne</p><h1>Žymos</h1>
      <form class="admin-tag-form" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="tag_add"><div class="field"><label for="new-tag">Naujos žymos pavadinimas</label><input id="new-tag" name="label" required maxlength="80"></div><button class="button" type="submit">Pridėti žymą</button></form>
      <div class="admin-tag-list"><?php foreach ($tags as $tag): ?>
      <form class="admin-tag-form" method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="tag_rename"><input type="hidden" name="id" value="<?= $tag['id'] ?>"><div class="field"><label for="tag-<?= $tag['id'] ?>">Žymos pavadinimas</label><input id="tag-<?= $tag['id'] ?>" name="label" value="<?= e($tag['label']) ?>" required maxlength="80"></div><button class="button button-secondary" type="submit">Pervadinti</button></form>
      <?php endforeach; ?></div>
    </section>
  <?php elseif ($edit !== null): ?>
    <section class="admin-panel"><p class="eyebrow"><?= $edit['id'] === '' ? 'Papildyk žodyną' : 'Peržiūrėk ir pataisyk' ?></p><h1><?= $edit['id'] === '' ? 'Naujas įrašas' : 'Redaguoti įrašą' ?></h1>
    <form method="post" class="admin-form">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($edit['id']) ?>">
      <?php foreach (['word' => ['Keiksmažodis', 120], 'meaning' => ['Reikšmė', 1200], 'example' => ['Vartojimo pavyzdys (nebūtina)', 600]] as $field => [$label, $maximum]): ?>
      <div class="field"><label for="<?= $field ?>"><?= e($label) ?></label><?php if ($field === 'word'): ?><input id="<?= $field ?>" name="<?= $field ?>" maxlength="<?= $maximum ?>" value="<?= e($edit[$field]) ?>" required><?php else: ?><textarea id="<?= $field ?>" name="<?= $field ?>" maxlength="<?= $maximum ?>" rows="<?= $field === 'meaning' ? 4 : 3 ?>" <?= $field === 'meaning' ? 'required' : '' ?>><?= e($edit[$field]) ?></textarea><?php endif; ?><?php if (isset($notice['errors'][$field])): ?><p class="admin-error"><?= e($notice['errors'][$field]) ?></p><?php endif; ?></div>
      <?php endforeach; ?>
      <fieldset class="admin-tag-options"><legend>Žymos</legend><?php foreach ($tags as $tag): ?><label><input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $edit['tags'], true) ? 'checked' : '' ?>> <?= e($tag['label']) ?></label><?php endforeach; ?><?php if (isset($notice['errors']['tags'])): ?><p class="admin-error"><?= e($notice['errors']['tags']) ?></p><?php endif; ?></fieldset>
      <div class="field"><label for="status">Būsena</label><select id="status" name="status"><?php foreach ($statusLabels as $key => $label): ?><option value="<?= $key ?>" <?= $edit['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="admin-actions"><button class="button" type="submit">Išsaugoti</button><a class="button button-secondary" href="/admin/">Atšaukti</a></div>
    </form></section>
  <?php else: ?>
    <section><div class="section-heading"><div><p class="eyebrow">Žodyno priežiūra</p><h1><?= e($statusLabels[$status]) ?></h1></div><p><?= $entryCount ?> <?= e($entryNoun) ?></p></div>
    <?php if ($rows === []): ?><div class="admin-panel admin-empty"><h2>Čia kol kas ramu.</h2><p><?= $status === 'pending' ? 'Nauji lankytojų pasiūlymai atsiras šiame sąraše.' : 'Šios būsenos įrašų dar nėra.' ?></p></div><?php endif; ?>
    <div class="admin-entry-list"><?php foreach ($rows as $row): ?>
      <article class="admin-panel admin-entry"><div class="admin-entry-top"><h2><?= e($row['word']) ?></h2><time datetime="<?= e(str_replace(' ', 'T', $row['created_at']) . 'Z') ?>"><?= e($row['created_at']) ?> UTC</time></div><p><?= nl2br(e($row['meaning'])) ?></p><?php if ($row['example'] !== ''): ?><blockquote><?= nl2br(e($row['example'])) ?></blockquote><?php endif; ?>
        <div class="admin-actions"><a class="button button-secondary button-small" href="/admin/?edit=<?= (int) $row['id'] ?>">Redaguoti</a>
        <?php foreach (['approved' => 'Patvirtinti', 'rejected' => 'Atmesti', 'pending' => 'Nebeskelbti'] as $target => $label): if ($target === $status || ($target === 'pending' && $status !== 'approved')) { continue; } ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><input type="hidden" name="expected_status" value="<?= e($row['status']) ?>"><input type="hidden" name="target_status" value="<?= $target ?>"><button class="button <?= $target === 'approved' ? '' : 'button-secondary' ?> button-small" type="submit"><?= $label ?></button></form>
        <?php endforeach; ?></div>
      </article>
    <?php endforeach; ?></div>
    <?php if ($pages > 1): ?><nav class="admin-pagination" aria-label="Puslapiai"><?php if ($page > 1): ?><a class="button button-secondary" href="/admin/?status=<?= $status ?>&amp;page=<?= $page - 1 ?>">Ankstesnis</a><?php endif; ?><span><?= $page ?> / <?= $pages ?></span><?php if ($page < $pages): ?><a class="button button-secondary" href="/admin/?status=<?= $status ?>&amp;page=<?= $page + 1 ?>">Kitas</a><?php endif; ?></nav><?php endif; ?>
    </section>
  <?php endif; ?>
  <?php endif; ?>
  <footer class="admin-footer"><a href="/keiksmazodziai/">← Grįžti į žodyną</a><span>Sesija baigiasi po 30 min. neveiklos.</span></footer>
</main>
</body>
</html>
