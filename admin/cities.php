<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('admin.manage');
$rows = $pdo->query('SELECT c.id, c.name_en, c.name_my, (SELECT COUNT(*) FROM cemeteries m WHERE m.city_id = c.id) AS n FROM cities c ORDER BY c.name_en')->fetchAll();
$pageTitle = t('cities');
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= $pageTitle ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head><body>
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/cities.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/cities.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('cities') ?></h1>
  </div>
</header>
<main class="container">
  <div style="margin:16px 20px 0"><a class="btn" href="/admin/city-edit.php" style="text-align:center;line-height:54px;text-decoration:none;margin:0"><?= t('add_city') ?></a></div>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <a class="card res" href="/admin/city-edit.php?id=<?= (int)$r['id'] ?>">
      <span class="grow"><b><?= htmlspecialchars(($lang === 'my' && $r['name_my']) ? $r['name_my'] : $r['name_en']) ?></b><br><span class="sm"><?= tf('cemetery_n', (int)$r['n']) ?></span></span>
    </a>
  <?php endforeach; ?>
  </div>
</main>
</body>
</html>