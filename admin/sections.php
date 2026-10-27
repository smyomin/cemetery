<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('block.edit');

$sql = 'SELECT s.id, s.code, s.lat, s.lng, m.name_en AS cem_en, m.name_my AS cem_my,
        (SELECT COUNT(*) FROM graves g WHERE g.cemetery_id = s.cemetery_id AND g.section = s.code) AS n
        FROM sections s JOIN cemeteries m ON m.id = s.cemetery_id';
$params = [];
if ($admin['cemetery_id'] !== null) {
    $sql .= ' WHERE s.cemetery_id = :c';
    $params[':c'] = $admin['cemetery_id'];
}
$sql .= ' ORDER BY m.name_en, s.code';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$pageTitle = t('blocks');
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
      <a href="/admin/sections.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/sections.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('blocks') ?></h1>
  </div>
</header>
<main class="container">
  <?php if (isset($_GET['deleted'])): ?><p class="ok" style="margin:16px 20px 0"><?= t('block_deleted') ?></p><?php endif; ?>
  <div style="margin:16px 20px 0"><a class="btn" href="/admin/section-add.php" style="text-align:center;line-height:54px;text-decoration:none;margin:0"><?= t('add_block') ?></a></div>
<?php if (!$rows): ?>
  <p class="empty"><?= t('no_blocks') ?></p>
<?php else: ?>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <a class="card res" href="/admin/section-edit.php?id=<?= (int)$r['id'] ?>">
      <span class="grow">
        <b><?= t('block') ?> <?= htmlspecialchars($r['code']) ?></b><br>
        <span class="sm"><?= htmlspecialchars(($lang === 'my' && $r['cem_my']) ? $r['cem_my'] : $r['cem_en']) ?> &middot; <?= tf('burial_n', (int)$r['n']) ?></span>
      </span>
      <span class="yr"><?= $r['lat'] !== null ? t('has_pin') : t('no_pin') ?></span>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>