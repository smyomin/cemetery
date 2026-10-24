<?php
require __DIR__ . '/includes/lang.php';
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('site_name') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/en/"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/my/"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <h1 class="ti"><?= t('site_name') ?></h1>
    <p class="su"><?= t('tagline') ?></p>
  </div>
</header>
<main class="container">
  <form class="card search-card" action="/<?= $lang ?>/search" method="get">
    <h2><?= t('find_grave') ?></h2>
    <input class="in" type="search" name="q" placeholder="<?= t('search_placeholder') ?>" aria-label="<?= t('search_placeholder') ?>">
    <button class="btn" type="submit"><?= t('search_button') ?></button>
  </form>
</main>
</body>
</html>
