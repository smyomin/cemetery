<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/lang.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT g.id, g.name_en, g.name_my, g.name_ar, g.father_name_en, g.father_name_my, g.father_name_ar, g.home_city_en, g.home_city_my, g.gender, g.age, g.died_on, g.hijri_date, g.section, g.plot, g.grave_no, g.person_photo, g.grave_photo, g.photo_consent, m.name_en AS cemetery_en, m.name_my AS cemetery_my,
            s.directions_en, s.directions_my,
            COALESCE(s.lat, m.lat) AS lat, COALESCE(s.lng, m.lng) AS lng
     FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id
     LEFT JOIN sections s ON s.cemetery_id = g.cemetery_id AND s.code = g.section
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
$dir = '';
$initial = '';
$showP = false;
$showG = false;
$hasPin = false;
if ($g) {
    $useMy = $lang === 'my' && $g['name_my'];
    $title = $useMy ? $g['name_my'] : $g['name_en'];
    $sub = $useMy ? $g['name_en'] : ($g['name_my'] ?? '');
    $cem = ($lang === 'my' && $g['cemetery_my']) ? $g['cemetery_my'] : $g['cemetery_en'];
    if ($g['died_on']) {
        $ts = strtotime($g['died_on']);
        $date = $lang === 'my' ? date('d-m-Y', $ts) : date('j F Y', $ts);
    }
    $dir = ($lang === 'my' && $g['directions_my']) ? $g['directions_my'] : ($g['directions_en'] ?? '');
    $showP = $g['person_photo'] && $g['photo_consent'] && is_file(__DIR__ . '/uploads/people/' . $g['person_photo']);
    $showG = $g['grave_photo'] && is_file(__DIR__ . '/uploads/graves/' . $g['grave_photo']);
    $hasPin = $g['lat'] !== null && $g['lng'] !== null;
    $initial = mb_strtoupper(mb_substr(preg_replace('/^(U|Daw|Ko|Ma|Mg|Maung)\s+/i', '', $g['name_en']), 0, 1));
}
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?: t('grave_not_found')) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">
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
      <div class="who">
        <?php if ($showP): ?><img class="avatar" src="/uploads/people/<?= htmlspecialchars($g['person_photo']) ?>" alt="<?= htmlspecialchars($title) ?>"><?php else: ?><span class="avatar avatar-i" aria-hidden="true"><?= htmlspecialchars($initial) ?></span><?php endif; ?>
        <div>
          <h1 class="ti" style="margin:0"><?= htmlspecialchars($title) ?></h1>
          <?php if ($sub): ?><p class="su"><?= htmlspecialchars($sub) ?></p><?php endif; ?>
          <?php if ($g['name_ar']): ?><p class="su ar" lang="ar" dir="rtl" style="text-align:left"><?= htmlspecialchars($g['name_ar']) ?></p><?php endif; ?>
        </div>
      </div>
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
    <?php if ($g['age'] !== null): ?><div class="drow"><span><?= t('age') ?></span><b><?= (int)$g['age'] ?></b></div><?php endif; ?>
    <?php if ($g['gender']): ?><div class="drow"><span><?= t('gender') ?></span><b><?= t($g['gender']) ?></b></div><?php endif; ?>
    <?php $fa = ($lang === 'my') ? ($g['father_name_my'] ?: $g['father_name_en']) : ($g['father_name_en'] ?: $g['father_name_my']); ?>
    <?php if ($fa || $g['father_name_ar']): ?><div class="drow"><span><?= t('father') ?></span><b><?= htmlspecialchars((string)$fa) ?><?php if ($g['father_name_ar']): ?><br><span class="ar" lang="ar" dir="rtl"><?= htmlspecialchars($g['father_name_ar']) ?></span><?php endif; ?></b></div><?php endif; ?>
    <?php $hc = ($lang === 'my') ? ($g['home_city_my'] ?: $g['home_city_en']) : ($g['home_city_en'] ?: $g['home_city_my']); ?>
    <?php if ($hc): ?><div class="drow"><span><?= t('hometown') ?></span><b><?= htmlspecialchars((string)$hc) ?></b></div><?php endif; ?>
    <div class="drow"><span><?= t('cemetery') ?></span><b><?= htmlspecialchars($cem) ?></b></div>
    <div class="drow"><span><?= t('section') ?></span><b><?= htmlspecialchars($g['section']) ?></b></div>
    <div class="drow"><span><?= t('plot') ?></span><b><?= htmlspecialchars($g['plot']) ?></b></div>
    <?php if ($g['grave_no']): ?><div class="drow"><span><?= t('grave_no') ?></span><b><?= htmlspecialchars($g['grave_no']) ?></b></div><?php endif; ?>
  </div>
  <?php if ($showG): ?>
  <a class="card photo-card" href="/uploads/graves/<?= htmlspecialchars($g['grave_photo']) ?>" target="_blank" rel="noopener">
    <img src="/uploads/graves/<?= htmlspecialchars($g['grave_photo']) ?>" alt="<?= t('grave_photo_alt') ?>" loading="lazy">
    <span class="sm"><?= t('grave_photo_alt') ?></span>
  </a>
  <?php endif; ?>
  <?php if ($dir || $hasPin): ?>
  <div class="card" style="margin:14px 20px 24px;padding:16px">
    <?php if ($dir): ?><h2 class="loc-h"><?= t('how_to_find') ?></h2><p class="loc-p"><?= nl2br(htmlspecialchars($dir)) ?></p><?php endif; ?>
    <?php if ($hasPin): ?><a class="btn" style="text-align:center;line-height:54px;text-decoration:none;margin-top:0" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= htmlspecialchars($g['lat'] . ',' . $g['lng']) ?>" target="_blank" rel="noopener"><?= t('directions_button') ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>