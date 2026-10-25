<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/lang.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT g.*, m.name_en AS cemetery_en, m.name_my AS cemetery_my, s.directions_en, s.directions_my, COALESCE(s.lat, m.lat) AS lat, COALESCE(s.lng, m.lng) AS lng
     FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id LEFT JOIN sections s ON s.cemetery_id = g.cemetery_id AND s.code = g.section
     WHERE g.id = :id'
);
$stmt->execute([':id' => $id]);
$g = $stmt->fetch();
if (!$g) {
    http_response_code(404);
}
$title = '';
$sub = '';
$cem = '';
$date = '';
if ($g) {
    $useMy = $lang === 'my' && $g['name_my'];
    $title = $useMy ? $g['name_my'] : $g['name_en'];
    $sub = $useMy ? $g['name_en'] : ($g['name_my'] ?? '');
    $cem = ($lang === 'my' && $g['cemetery_my']) ? $g['cemetery_my'] : $g['cemetery_en'];
    if ($g['died_on']) {
        $ts = strtotime($g['died_on']);
        $date = $lang === 'my' ? date('d-m-Y', $ts) : date('j F Y', $ts);
    }
}
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?: t('grave_not_found')) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/en/grave/<?= $id ?>"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/my/grave/<?= $id ?>"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/<?= $lang ?>/">&larr; <?= t('home') ?></a>
    <?php if ($g): ?>
      <h1 class="ti"><?= htmlspecialchars($title) ?></h1>
      <?php if ($sub): ?><p class="su"><?= htmlspecialchars($sub) ?></p><?php endif; ?>
    <?php else: ?>
      <h1 class="ti"><?= t('grave_not_found') ?></h1>
    <?php endif; ?>
  </div>
</header>
<main class="container">
<?php if ($g): ?>
  <div class="card grave-card">
    <?php if ($date): ?><div class="drow"><span><?= t('died') ?></span><b><?= htmlspecialchars($date) ?></b></div><?php endif; ?>
    <?php if ($g['hijri_date']): ?><div class="drow"><span><?= t('hijri') ?></span><b><?= htmlspecialchars($g['hijri_date']) ?></b></div><?php endif; ?>
    <div class="drow"><span><?= t('cemetery') ?></span><b><?= htmlspecialchars($cem) ?></b></div>
    <div class="drow"><span><?= t('section') ?></span><b><?= htmlspecialchars($g['section']) ?></b></div>
    <div class="drow"><span><?= t('plot') ?></span><b><?= htmlspecialchars($g['plot']) ?></b></div>
    <?php if ($g['grave_no']): ?><div class="drow"><span><?= t('grave_no') ?></span><b><?= htmlspecialchars($g['grave_no']) ?></b></div><?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($g): ?>
<?php $dir = ($lang === 'my' && $g['directions_my']) ? $g['directions_my'] : ($g['directions_en'] ?? ''); ?>
<?php if ($dir || $g['lat'] !== null): ?>
  <div class="card" style="margin:14px 20px 24px;padding:16px">
    <?php if ($dir): ?><h2 class="loc-h"><?= t('how_to_find') ?></h2><p class="loc-p"><?= nl2br(htmlspecialchars($dir)) ?></p><?php endif; ?>
    <?php if ($g['lat'] !== null && $g['lng'] !== null): ?><a class="btn" style="text-align:center;line-height:54px;text-decoration:none;margin-top:0" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= htmlspecialchars($g['lat'] . ',' . $g['lng']) ?>" target="_blank" rel="noopener"><?= t('directions_button') ?></a><?php endif; ?>
  </div>
<?php endif; ?>
<?php endif; ?></main>
</body>
</html>
