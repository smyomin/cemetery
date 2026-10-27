<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('admin.manage');

$rows = $pdo->query(
    'SELECT a.id, a.username, a.role, a.active, m.name_en AS cem_en, m.name_my AS cem_my
     FROM admins a LEFT JOIN cemeteries m ON m.id = a.cemetery_id
     ORDER BY a.active DESC, a.username'
)->fetchAll();
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('admins') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/admins.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/admins.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('admins') ?></h1>
  </div>
</header>
<main class="container">
  <div style="margin:16px 20px 0"><a class="btn" href="/admin/admin-edit.php" style="text-align:center;line-height:54px;text-decoration:none;margin:0"><?= t('add_admin') ?></a></div>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <a class="card res" href="/admin/admin-edit.php?id=<?= (int)$r['id'] ?>">
      <span class="grow">
        <b><?= htmlspecialchars($r['username']) ?><?= (int)$r['id'] === $admin['id'] ? ' (' . t('you') . ')' : '' ?></b><br>
        <span class="sm"><?= t('role_' . $r['role']) ?> · <?= $r['role'] === 'super_admin' ? t('all_cemeteries') : htmlspecialchars(($lang === 'my' && $r['cem_my']) ? $r['cem_my'] : (string)$r['cem_en']) ?></span>
      </span>
      <?php if (!(int)$r['active']): ?><span class="yr"><?= t('inactive') ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  </div>
</main>
</body>
</html>