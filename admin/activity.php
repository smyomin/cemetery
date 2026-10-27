<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('admin.manage');

$rows = $pdo->query(
    'SELECT l.created_at, l.admin_username, l.action, l.details, m.name_en AS cem_en, m.name_my AS cem_my
     FROM activity_log l LEFT JOIN cemeteries m ON m.id = l.cemetery_id
     ORDER BY l.id DESC LIMIT 200'
)->fetchAll();
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('activity') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/activity.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/activity.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('activity') ?></h1>
  </div>
</header>
<main class="container">
<?php if (!$rows): ?>
  <p class="empty"><?= t('no_activity') ?></p>
<?php else: ?>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <div class="card" style="padding:12px 14px">
      <b><?= t('act_' . $r['action']) ?></b><?php if ($r['details']): ?> &middot; <?= htmlspecialchars($r['details']) ?><?php endif; ?><br>
      <span class="sm"><?= htmlspecialchars($r['admin_username']) ?> &middot; <?= htmlspecialchars($r['created_at']) ?><?php if ($r['cem_en']): ?> &middot; <?= htmlspecialchars(($lang === 'my' && $r['cem_my']) ? $r['cem_my'] : $r['cem_en']) ?><?php endif; ?></span>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>