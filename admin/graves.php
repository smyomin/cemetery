<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();

$sql = 'SELECT g.id, g.name_en, g.name_my, g.section, g.plot, m.name_en AS cemetery_en, m.name_my AS cemetery_my
        FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id';
$params = [];
if ($admin['cemetery_id'] !== null) {
    $sql .= ' WHERE g.cemetery_id = :c';
    $params[':c'] = $admin['cemetery_id'];
}
$sql .= ' ORDER BY g.id DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('burial_list') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/graves.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/graves.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('burial_list') ?></h1>
    <p class="su"><?= tf('results_found', count($rows)) ?></p>
  </div>
</header>
<main class="container">
<?php if (!$rows): ?>
  <p class="empty"><?= t('no_burials') ?></p>
<?php else: ?>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <a class="card res" href="/<?= $lang ?>/grave/<?= (int)$r['id'] ?>">
      <span class="grow">
        <b><?= htmlspecialchars(($lang === 'my' && $r['name_my']) ? $r['name_my'] : $r['name_en']) ?></b><br>
        <span class="sm"><?= htmlspecialchars(($lang === 'my' && $r['cemetery_my']) ? $r['cemetery_my'] : $r['cemetery_en']) ?> · <?= t('section') ?> <?= htmlspecialchars($r['section']) ?>, <?= t('plot') ?> <?= htmlspecialchars($r['plot']) ?></span>
      </span>
      <span class="yr"><?= t('view') ?></span>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>