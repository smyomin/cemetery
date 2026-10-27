<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('admin') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <h1 class="ti"><?= t('admin') ?></h1>
    <p class="su"><?= tf('logged_in_as', $admin['username']) ?></p>
  </div>
</header>
<main class="container">
  <div class="card search-card">
    <a class="btn" href="/admin/grave-add.php" style="text-align:center;line-height:54px;text-decoration:none;margin:0 0 12px"><?= t('add_burial') ?></a>
    <a class="btn" href="/admin/graves.php" style="text-align:center;line-height:54px;text-decoration:none;margin:0 0 12px"><?= t('burial_list') ?></a>
    <?php if (can('block.edit')): ?><a class="btn" href="/admin/sections.php" style="text-align:center;line-height:54px;text-decoration:none;margin:0 0 12px"><?= t('blocks') ?></a><?php endif; ?>
    <a class="btn" href="/logout.php" style="text-align:center;line-height:54px;text-decoration:none"><?= t('logout') ?></a>
  </div>
</main>
</body>
</html>