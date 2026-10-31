<?php
declare(strict_types=1);

namespace Blynine;

require_once __DIR__ . '/app.php';

function dictionary_url(string $query = '', string $tag = '', int $page = 1): string
{
    $params = [];
    if ($query !== '') $params['q'] = $query;
    if ($tag !== '') $params['tag'] = $tag;
    if ($page > 1) $params['page'] = $page;
    return '/keiksmazodziai/' . ($params ? '?' . http_build_query($params) : '') . '#zodynas';
}

function word_count_label(int $total): string
{
    if ($total % 10 === 1 && $total % 100 !== 11) return 'įrašas';
    if ($total % 10 >= 2 && $total % 10 <= 9 && ($total % 100 < 11 || $total % 100 > 19)) return 'įrašai';
    return 'įrašų';
}

function render_dictionary(): void
{
    start_session();
    header('Cache-Control: no-store, max-age=0');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
    $tag = is_string($_GET['tag'] ?? null) ? mb_substr($_GET['tag'], 0, 100) : '';
    $pageInput = is_string($_GET['page'] ?? null) ? $_GET['page'] : '1';
    $page = max(1, min(100000, (int) (filter_var($pageInput, FILTER_VALIDATE_INT) ?: 1)));
    $data = ['entries' => [], 'tags' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
    $unavailable = false;
    try {
        $data = dictionary_data($query, $tag, $page);
    } catch (\Throwable $error) {
        error_log('blynine: dictionary is unavailable (' . get_class($error) . ').');
        http_response_code(503);
        $unavailable = true;
    }
    $flash = take_flash();
    $old = is_array($flash['old'] ?? null) ? $flash['old'] : [];
    $errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
    $oldTags = is_array($old['tags'] ?? null) ? array_map('strval', $old['tags']) : [];
    $fieldError = static function (string $field) use ($errors): string {
        $value = $errors[$field] ?? '';
        return is_string($value) ? $value : '';
    };
    $oldValue = static function (string $field) use ($old): string {
        return is_string($old[$field] ?? null) ? $old[$field] : '';
    };
    ?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Keiksmų žodynas — blyninė</title>
  <meta name="description" content="Lietuvoje vartojami keiksmai, jų reikšmės ir kontekstas. Naršyk gyvą žodyną arba pasiūlyk savo žodį.">
  <meta name="theme-color" content="#f5f2e9">
  <link rel="icon" href="/assets/mark.svg" type="image/svg+xml">
  <link rel="preload" href="/assets/fonts/manrope-variable.ttf" as="font" type="font/ttf" crossorigin>
  <link rel="stylesheet" href="/assets/site.css">
  <script src="/assets/site.js" defer></script>
</head>
<body>
  <a class="skip-link" href="#zodynas">Pereiti prie žodyno</a>
  <header class="site-header container">
    <a class="brand" href="/" aria-label="Blyninė — naujas atsitiktinis puslapis"><img class="brand-mark" src="/assets/mark.svg" alt="" width="39" height="39">blyninė</a>
    <nav class="site-nav" aria-label="Pagrindinė navigacija">
      <a href="#zodynas">Žodynas</a>
      <?php if (!$unavailable): ?><a class="nav-submit" href="#pasiulyti">Pasiūlyti žodį <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 18 18 6M6 6h12v12" stroke="currentColor" stroke-width="1.7"/></svg></a><?php endif; ?>
      <a class="nav-reroll" href="/" aria-label="Atidaryti kitą atsitiktinę blyninę">Kita blyninė <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M5.7 7.5A7 7 0 0 1 18.6 8M18.3 16.5A7 7 0 0 1 5.4 16" stroke="currentColor" stroke-width="1.7"/></svg></a>
    </nav>
  </header>
  <main>
    <section class="hero container" aria-labelledby="dictionary-title">
      <p class="eyebrow">Kalbos paraštės / keiksmų žodynas</p>
      <h1 id="dictionary-title">Kartais tiesiog<br><span>išsprūsta.</span></h1>
      <div class="hero-bottom">
        <p class="hero-description">Lietuvoje vartojami keiksmai, jų reikšmės ir kontekstas. Surask pažįstamą arba pasiūlyk dar negirdėtą.</p>
        <div class="hero-side"><span>Gyva kalba.<br>Be pagražinimų.</span><svg class="hero-flip" viewBox="0 0 100 100" fill="none" aria-hidden="true"><ellipse cx="36" cy="22" rx="20" ry="7" transform="rotate(-23 36 22)" stroke="currentColor" stroke-width="3"/><path d="M13 61c3 12 15 20 31 20s28-8 31-20H13Zm62 0 19-14M64 22c13 5 19 15 17 26m-6-5 6 5 5-6" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      </div>
    </section>
    <?php if ($unavailable): ?>
    <section id="zodynas" class="service-unavailable container" role="status">
      <p class="eyebrow">Trumpa pertrauka</p>
      <h2>Žodynas trumpam užstrigo.</h2>
      <p>Šiuo metu negalime įkelti įrašų. Pabandyk dar kartą po kelių minučių.</p>
      <a class="button" href="/keiksmazodziai/">Bandyti dar kartą <span aria-hidden="true">↗</span></a>
    </section>
    <?php else: ?>
    <section id="zodynas" class="dictionary-section container" aria-labelledby="words-heading">
      <div class="section-heading"><h2 id="words-heading">Žodynas</h2><span class="section-count"><?= e((string) $data['total']) ?> <?= e(word_count_label((int) $data['total'])) ?></span></div>
      <div class="dictionary-tools">
        <form class="search-form" action="/keiksmazodziai/#zodynas" method="get" role="search">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.5"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.5"/></svg>
          <label class="sr-only" for="dictionary-search">Ieškoti žodžio arba reikšmės</label>
          <input type="search" id="dictionary-search" name="q" maxlength="100" placeholder="Ieškok žodžio arba reikšmės" value="<?= e($query) ?>">
          <?php if ($tag !== ''): ?><input type="hidden" name="tag" value="<?= e($tag) ?>"><?php endif; ?>
          <button type="submit">Ieškoti</button>
        </form>
        <nav class="tag-filters" aria-label="Filtruoti pagal žymą">
          <a href="<?= e(dictionary_url($query)) ?>"<?= $tag === '' ? ' aria-current="true"' : '' ?>>Visi</a>
          <?php foreach ($data['tags'] as $filterTag): ?>
          <a href="<?= e(dictionary_url($query, $filterTag['slug'])) ?>"<?= $tag === $filterTag['slug'] ? ' aria-current="true"' : '' ?>><?= e($filterTag['label']) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <?php if ($query !== ''): ?><p class="filter-summary">Paieškos rezultatai: „<?= e($query) ?>“ <a href="<?= e(dictionary_url('', $tag)) ?>">Išvalyti paiešką</a></p><?php endif; ?>
      <?php if ($data['entries'] === []): ?>
      <div class="empty-state"><h3><?= $query !== '' || $tag !== '' ? 'Šito dar negirdėjome.' : 'Pirmas žodis gali būti tavo.' ?></h3><p><?= $query !== '' || $tag !== '' ? 'Pabandyk kitą paiešką arba pasiūlyk žodį žodynui.' : 'Žodynas laukia pirmųjų patvirtintų įrašų. Pasiūlyk žodį — po peržiūros jis atsiras čia.' ?></p><a href="#pasiulyti">Pasiūlyti žodį ↗</a></div>
      <?php else: ?>
      <div class="word-list">
        <?php foreach ($data['entries'] as $index => $entry): $rowNumber = ((int) $data['page'] - 1) * (int) ($data['per_page'] ?? 18) + $index + 1; ?>
        <article class="word-entry" id="zodis-<?= e((string) $entry['id']) ?>">
          <span class="word-index" aria-hidden="true"><?= e(str_pad((string) $rowNumber, 2, '0', STR_PAD_LEFT)) ?></span>
          <div class="word-name"><h3 class="word-title"><?= e($entry['word']) ?></h3><div class="word-tags"><?php foreach ($entry['tags'] as $entryTag): ?><a class="tag" href="<?= e(dictionary_url('', $entryTag['slug'])) ?>"><?= e($entryTag['label']) ?></a><?php endforeach; ?></div></div>
          <div class="word-detail"><p class="word-meaning"><?= nl2br(e($entry['meaning'])) ?></p><?php if (trim((string) $entry['example']) !== ''): ?><blockquote class="word-example">„<?= e($entry['example']) ?>“</blockquote><?php endif; ?></div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ((int) $data['pages'] > 1): ?>
      <nav class="pagination" aria-label="Žodyno puslapiai">
        <?php if ((int) $data['page'] > 1): ?><a href="<?= e(dictionary_url($query, $tag, (int) $data['page'] - 1)) ?>">← Ankstesnis</a><?php else: ?><span class="disabled">← Ankstesnis</span><?php endif; ?>
        <span><?= e((string) $data['page']) ?> / <?= e((string) $data['pages']) ?></span>
        <?php if ((int) $data['page'] < (int) $data['pages']): ?><a href="<?= e(dictionary_url($query, $tag, (int) $data['page'] + 1)) ?>">Kitas →</a><?php else: ?><span class="disabled">Kitas →</span><?php endif; ?>
      </nav>
      <?php endif; ?>
    </section>
    <section id="pasiulyti" class="submission-wrap" aria-labelledby="submit-heading">
      <div class="submission-layout container">
        <div class="submission-intro"><p class="eyebrow">Papildyk žodyną</p><h2 id="submit-heading">Tavo<br>žodis.</h2><p>Gal tavo krašte keikiamasi kitaip?<br>Pasidalink žodžiu, jo reikšme ir sakiniu, kuriame jis išsprūstų.</p><div class="submission-note"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="1.6"/></svg><span>Kiekvieną pasiūlymą peržiūrime. Viešai matomi tik patvirtinti įrašai.</span></div></div>
        <form class="submission-form" action="/keiksmazodziai/pateikti.php" method="post" data-submit-once>
          <?php if (is_string($flash['message'] ?? null) && $flash['message'] !== ''): ?><div class="notice <?= ($flash['kind'] ?? '') === 'error' ? 'notice-error' : 'notice-success' ?>" role="<?= ($flash['kind'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="sr-only" aria-hidden="true"><label for="website">Svetainė</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="field"><label for="word">Žodis ar posakis</label><input id="word" name="word" required maxlength="120" placeholder="Tas, kurio čia dar nėra" value="<?= e($oldValue('word')) ?>"<?= $fieldError('word') !== '' ? ' aria-invalid="true" aria-describedby="word-error"' : '' ?>><?php if ($fieldError('word') !== ''): ?><p class="field-error" id="word-error"><?= e($fieldError('word')) ?></p><?php endif; ?></div>
          <div class="field"><label for="meaning">Ką jis reiškia?</label><textarea id="meaning" name="meaning" required maxlength="1200" rows="2" placeholder="Trumpai paaiškink savais žodžiais"<?= $fieldError('meaning') !== '' ? ' aria-invalid="true" aria-describedby="meaning-error"' : '' ?>><?= e($oldValue('meaning')) ?></textarea><?php if ($fieldError('meaning') !== ''): ?><p class="field-error" id="meaning-error"><?= e($fieldError('meaning')) ?></p><?php endif; ?></div>
          <div class="field"><label for="example">Pavyzdys <span> / nebūtina</span></label><input id="example" name="example" maxlength="600" placeholder="Kaip tai skambėtų sakinyje?" value="<?= e($oldValue('example')) ?>"<?= $fieldError('example') !== '' ? ' aria-invalid="true" aria-describedby="example-error"' : '' ?>><?php if ($fieldError('example') !== ''): ?><p class="field-error" id="example-error"><?= e($fieldError('example')) ?></p><?php endif; ?></div>
          <fieldset class="tag-options"><legend>Žymos <span> / nebūtina</span></legend><div class="tag-option-list"><?php foreach ($data['tags'] as $formTag): ?><label class="tag-option"><input type="checkbox" name="tags[]" value="<?= e((string) $formTag['id']) ?>"<?= in_array((string) $formTag['id'], $oldTags, true) ? ' checked' : '' ?>><?= e($formTag['label']) ?></label><?php endforeach; ?></div><?php if ($fieldError('tags') !== ''): ?><p class="field-error"><?= e($fieldError('tags')) ?></p><?php endif; ?></fieldset>
          <button class="button" type="submit">Pateikti peržiūrai <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.6"/></svg></button>
          <p class="form-bottom">Prisijungti nereikia. Pasiūlymą peržiūrės administratorius.</p>
        </form>
      </div>
    </section>
    <?php endif; ?>
  </main>
  <footer class="site-footer container"><div class="footer-brand"><a class="brand" href="/" aria-label="Blyninė — naujas atsitiktinis puslapis"><img class="brand-mark" src="/assets/mark.svg" alt="" width="29" height="29">blyninė</a><p>Kaskart gali iškepti kas nors kito.</p></div><nav class="footer-links" aria-label="Papildoma navigacija"><a href="/">Kita blyninė ↗</a><a href="/zaidimas/">Pavartyti blyną</a><a href="/admin/">Administravimas</a></nav></footer>
</body>
</html>
    <?php
}
