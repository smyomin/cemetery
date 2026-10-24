<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT s.*, m.name_en AS cem_en, m.name_my AS cem_my FROM sections s JOIN cemeteries m ON m.id = s.cemetery_id WHERE s.id = :id');
$stmt->execute([':id' => $id]);
$s = $stmt->fetch();
if (!$s || ($admin['cemetery_id'] !== null && (int)$s['cemetery_id'] !== $admin['cemetery_id'])) {
    http_response_code(404);
    exit(t('not_found'));
}

$err = '';
$v = [
    'directions_en' => $s['directions_en'] ?? '',
    'directions_my' => $s['directions_my'] ?? '',
    'lat' => $s['lat'] === null ? '' : rtrim(rtrim($s['lat'], '0'), '.'),
    'lng' => $s['lng'] === null ? '' : rtrim(rtrim($s['lng'], '0'), '.'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $gpsGiven = $v['lat'] !== '' || $v['lng'] !== '';
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (mb_strlen($v['directions_en']) > 1000 || mb_strlen($v['directions_my']) > 1000) {
        $err = 'err_long';
    } elseif ($gpsGiven && (!is_numeric($v['lat']) || !is_numeric($v['lng']) || abs((float)$v['lat']) > 90 || abs((float)$v['lng']) > 180)) {
        $err = 'err_gps';
    } else {
        $u = $pdo->prepare('UPDATE sections SET directions_en = :de, directions_my = :dm, lat = :lat, lng = :lng WHERE id = :id');
        $u->execute([
            ':de' => $v['directions_en'] === '' ? null : $v['directions_en'],
            ':dm' => $v['directions_my'] === '' ? null : $v['directions_my'],
            ':lat' => $gpsGiven ? round((float)$v['lat'], 6) : null,
            ':lng' => $gpsGiven ? round((float)$v['lng'], 6) : null,
            ':id' => $id,
        ]);
        header('Location: /admin/section-edit.php?id=' . $id . '&updated=1');
        exit;
    }
}
$cem = ($lang === 'my' && $s['cem_my']) ? $s['cem_my'] : $s['cem_en'];
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('edit_block') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/section-edit.php?id=<?= $id ?>&amp;lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/section-edit.php?id=<?= $id ?>&amp;lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/sections.php">&larr; <?= t('blocks') ?></a>
    <h1 class="ti"><?= t('block') ?> <?= htmlspecialchars($s['code']) ?></h1>
    <p class="su"><?= htmlspecialchars($cem) ?></p>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/section-edit.php?id=<?= $id ?>">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if (isset($_GET['updated'])): ?><p class="ok"><?= t('changes_saved') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="directions_en"><?= t('directions_en') ?></label>
    <textarea class="in" id="directions_en" name="directions_en" rows="4" maxlength="1000" style="height:auto;padding:12px 16px"><?= htmlspecialchars($v['directions_en']) ?></textarea>

    <label class="lbl" for="directions_my"><?= t('directions_my') ?></label>
    <textarea class="in" id="directions_my" name="directions_my" rows="4" maxlength="1000" style="height:auto;padding:12px 16px"><?= htmlspecialchars($v['directions_my']) ?></textarea>

    <p class="sm" style="margin:16px 0 0"><?= t('gps_hint') ?></p>
    <button class="btn" type="button" id="loc" style="background:#fff;color:#0F4C3A;border:1.5px solid #0F4C3A"><?= t('use_location') ?></button>

    <label class="lbl" for="lat"><?= t('latitude') ?></label>
    <input class="in" id="lat" name="lat" inputmode="decimal" value="<?= htmlspecialchars($v['lat']) ?>">

    <label class="lbl" for="lng"><?= t('longitude') ?></label>
    <input class="in" id="lng" name="lng" inputmode="decimal" value="<?= htmlspecialchars($v['lng']) ?>">

    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
</main>
<script>
document.getElementById('loc').addEventListener('click', function () {
  if (!navigator.geolocation) { return; }
  navigator.geolocation.getCurrentPosition(function (p) {
    document.getElementById('lat').value = p.coords.latitude.toFixed(6);
    document.getElementById('lng').value = p.coords.longitude.toFixed(6);
  });
});
</script>
</body>
</html>