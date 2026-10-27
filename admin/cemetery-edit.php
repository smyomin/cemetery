<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('admin.manage');

$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM cemeteries WHERE id = :id');
    $s->execute([':id' => $id]);
    $row = $s->fetch();
    if (!$row) { http_response_code(404); exit(t('not_found')); }
}
$cities = $pdo->query('SELECT id, name_en, name_my FROM cities ORDER BY name_en')->fetchAll();
$validCities = array_map(fn($c) => (int)$c['id'], $cities);
$trim = fn($x) => ($x === null) ? '' : rtrim(rtrim((string)$x, '0'), '.');

$err = '';
$v = [
    'city_id' => (int)($row['city_id'] ?? 0),
    'name_en' => $row['name_en'] ?? '',
    'name_my' => $row['name_my'] ?? '',
    'sect' => $row['sect'] ?? '',
    'slug' => $row['slug'] ?? '',
    'lat' => $trim($row['lat'] ?? null),
    'lng' => $trim($row['lng'] ?? null),
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['city_id'] = (int)($_POST['city_id'] ?? 0);
    $v['name_en'] = trim($_POST['name_en'] ?? '');
    $v['name_my'] = trim($_POST['name_my'] ?? '');
    $v['sect'] = trim($_POST['sect'] ?? '');
    $v['slug'] = $row ? $row['slug'] : strtolower(trim($_POST['slug'] ?? ''));
    $v['lat'] = trim($_POST['lat'] ?? '');
    $v['lng'] = trim($_POST['lng'] ?? '');
    $gps = $v['lat'] !== '' || $v['lng'] !== '';
    $taken = false;
    if (!$row) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM cemeteries WHERE slug = :s');
        $c->execute([':s' => $v['slug']]);
        $taken = (int)$c->fetchColumn() > 0;
    }
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (!in_array($v['city_id'], $validCities, true)) {
        $err = 'err_city';
    } elseif ($v['name_en'] === '' || mb_strlen($v['name_en']) > 150 || mb_strlen($v['name_my']) > 150) {
        $err = 'err_name';
    } elseif ($v['sect'] === '' || mb_strlen($v['sect']) > 30) {
        $err = 'err_sect';
    } elseif (!$row && !preg_match('/^[a-z0-9-]{2,60}$/', $v['slug'])) {
        $err = 'err_slug';
    } elseif ($taken) {
        $err = 'err_slug_taken';
    } elseif ($gps && (!is_numeric($v['lat']) || !is_numeric($v['lng']) || abs((float)$v['lat']) > 90 || abs((float)$v['lng']) > 180)) {
        $err = 'err_gps';
    } else {
        $my = $v['name_my'] === '' ? null : $v['name_my'];
        $lat = $gps ? round((float)$v['lat'], 6) : null;
        $lng = $gps ? round((float)$v['lng'], 6) : null;
        if ($row) {
            $pdo->prepare('UPDATE cemeteries SET city_id = :c, name_en = :e, name_my = :m, sect = :t, lat = :la, lng = :lo WHERE id = :id')
                ->execute([':c' => $v['city_id'], ':e' => $v['name_en'], ':m' => $my, ':t' => $v['sect'], ':la' => $lat, ':lo' => $lng, ':id' => $id]);
            log_activity($pdo, 'cemetery.update', 'cemetery', $id, $id, $v['name_en']);
            header('Location: /admin/cemetery-edit.php?id=' . $id . '&saved=1');
        } else {
            $pdo->prepare('INSERT INTO cemeteries (city_id, name_en, name_my, sect, slug, lat, lng) VALUES (:c, :e, :m, :t, :s, :la, :lo)')
                ->execute([':c' => $v['city_id'], ':e' => $v['name_en'], ':m' => $my, ':t' => $v['sect'], ':s' => $v['slug'], ':la' => $lat, ':lo' => $lng]);
            $newId = (int)$pdo->lastInsertId();
            log_activity($pdo, 'cemetery.create', 'cemetery', $newId, $newId, $v['name_en']);
            header('Location: /admin/cemetery-edit.php?id=' . $newId . '&saved=1');
        }
        exit;
    }
}
$pageTitle = $row ? t('edit_cemetery') : t('add_cemetery');
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
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/cemetery-edit.php?id=<?= $id ?>&amp;lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/cemetery-edit.php?id=<?= $id ?>&amp;lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/cemeteries.php">&larr; <?= t('cemeteries') ?></a>
    <h1 class="ti"><?= $pageTitle ?></h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/cemetery-edit.php?id=<?= $id ?>">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if (isset($_GET['saved'])): ?><p class="ok"><?= t('changes_saved') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="city_id"><?= t('city') ?></label>
    <select class="in" id="city_id" name="city_id">
      <option value="0"><?= t('choose_cemetery') ?></option>
      <?php foreach ($cities as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $v['city_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= htmlspecialchars(($lang === 'my' && $c['name_my']) ? $c['name_my'] : $c['name_en']) ?></option><?php endforeach; ?>
    </select>

    <label class="lbl" for="name_en"><?= t('name_en') ?></label>
    <input class="in" id="name_en" name="name_en" value="<?= htmlspecialchars($v['name_en']) ?>" maxlength="150" required>

    <label class="lbl" for="name_my"><?= t('name_my') ?></label>
    <input class="in" id="name_my" name="name_my" value="<?= htmlspecialchars($v['name_my']) ?>" maxlength="150">

    <label class="lbl" for="sect"><?= t('sect_label') ?></label>
    <input class="in" id="sect" name="sect" list="sects" value="<?= htmlspecialchars($v['sect']) ?>" maxlength="30" required>
    <datalist id="sects"><option value="Shia"><option value="Sunni"></datalist>

    <label class="lbl" for="slug"><?= t('slug') ?></label>
    <input class="in" id="slug" name="slug" value="<?= htmlspecialchars($v['slug']) ?>" maxlength="60"<?= $row ? ' readonly' : ' required' ?>>

    <p class="sm" style="margin:16px 0 0"><?= t('cemetery_pin_hint') ?> <?= t('gps_hint') ?></p>
    <label class="lbl" for="lat"><?= t('latitude') ?></label>
    <input class="in" id="lat" name="lat" inputmode="decimal" value="<?= htmlspecialchars($v['lat']) ?>">
    <label class="lbl" for="lng"><?= t('longitude') ?></label>
    <input class="in" id="lng" name="lng" inputmode="decimal" value="<?= htmlspecialchars($v['lng']) ?>">

    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
</main>
<script src="/assets/js/coords.js"></script>
<script>attachCoords('lat', 'lng');</script>
</body>
</html>