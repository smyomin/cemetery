<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
require __DIR__ . '/../includes/qr.php';
require __DIR__ . '/../includes/photo.php';
$admin = require_admin();
require_can('burial.create');

$cems = $pdo->query('SELECT id, name_en, name_my FROM cemeteries ORDER BY name_en')->fetchAll();
if (admin_cemetery_ids() !== null) {
    $cems = array_values(array_filter($cems, fn($c) => can_access_cemetery((int)$c['id'])));
}
$allowed = array_map(fn($c) => (int)$c['id'], $cems);

$err = '';
$v = ['cemetery_id' => $allowed[0] ?? 0, 'name_en' => '', 'name_my' => '', 'died_on' => '', 'hijri_date' => '', 'section' => '', 'plot' => '', 'grave_no' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $v['cemetery_id'] = (int)$v['cemetery_id'];
    $d = DateTime::createFromFormat('Y-m-d', $v['died_on']);
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (!in_array($v['cemetery_id'], $allowed, true)) {
        $err = 'err_cemetery';
    } elseif ($v['name_en'] === '' || mb_strlen($v['name_en']) > 150 || mb_strlen($v['name_my']) > 150) {
        $err = 'err_name';
    } elseif ($v['section'] === '' || $v['plot'] === '' || mb_strlen($v['section']) > 10 || mb_strlen($v['plot']) > 10) {
        $err = 'err_secplot';
    } elseif ($v['died_on'] !== '' && (!$d || $d->format('Y-m-d') !== $v['died_on'] || $d > new DateTime('today'))) {
        $err = 'err_date';
    } elseif (mb_strlen($v['hijri_date']) > 40) {
        $err = 'err_hijri';
    } elseif (mb_strlen($v['grave_no']) > 20) {
        $err = 'err_grave_no';
    } elseif ($v['grave_no'] !== '' && grave_no_taken($pdo, $v['cemetery_id'], $v['grave_no'], 0)) {
        $err = 'err_dup';
    } else {
        $stmt = $pdo->prepare('INSERT INTO graves (cemetery_id, name_en, name_my, died_on, hijri_date, section, plot, grave_no) VALUES (:c, :en, :my, :d, :h, :s, :p, :g)');
        $stmt->execute([
            ':c' => $v['cemetery_id'],
            ':en' => $v['name_en'],
            ':my' => $v['name_my'] === '' ? null : $v['name_my'],
            ':d' => $v['died_on'] === '' ? null : $v['died_on'],
            ':h' => $v['hijri_date'] === '' ? null : $v['hijri_date'],
            ':s' => $v['section'],
            ':p' => $v['plot'],
            ':g' => $v['grave_no'] === '' ? null : $v['grave_no'],
        ]);
        $newId = $pdo->lastInsertId();
        log_activity($pdo, 'burial.create', 'burial', (int)$newId, $v['cemetery_id'], $v['name_en']);
        $consent = isset($_POST['photo_consent']) ? 1 : 0;
        [$pp, $e1] = save_photo($_FILES['person_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'people');
        [$gp, $e2] = save_photo($_FILES['grave_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'graves');
        $pdo->prepare('UPDATE graves SET person_photo = :pp, grave_photo = :gp, photo_consent = :pc WHERE id = :id')->execute([':pp' => $pp, ':gp' => $gp, ':pc' => $consent, ':id' => $newId]);
        $q = ($e1 || $e2) ? '&photoerr=1' : '';
        $pdo->prepare('INSERT IGNORE INTO sections (cemetery_id, code) VALUES (:c, :s)')->execute([':c' => $v['cemetery_id'], ':s' => $v['section']]);
        header('Location: /admin/grave-add.php?saved=' . $newId . $q);
        exit;
    }
}
$savedId = (int)($_GET['saved'] ?? 0);
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('add_burial') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/grave-add.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/grave-add.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('add_burial') ?></h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/grave-add.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($savedId): ?><p class="ok"><?= t('saved') ?> <a href="/<?= $lang ?>/grave/<?= $savedId ?>"><?= t('view_grave') ?></a></p><?php endif; ?>
    <?php if (isset($_GET['photoerr'])): ?><p class="err"><?= t('err_photo') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="cemetery_id"><?= t('cemetery') ?></label>
    <select class="in" id="cemetery_id" name="cemetery_id">
      <?php foreach ($cems as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $v['cemetery_id'] ? ' selected' : '' ?>><?= htmlspecialchars(($lang === 'my' && $c['name_my']) ? $c['name_my'] : $c['name_en']) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="lbl" for="name_en"><?= t('name_en') ?></label>
    <input class="in" id="name_en" name="name_en" value="<?= htmlspecialchars($v['name_en']) ?>" maxlength="150" required>

    <label class="lbl" for="name_my"><?= t('name_my') ?></label>
    <input class="in" id="name_my" name="name_my" value="<?= htmlspecialchars($v['name_my']) ?>" maxlength="150">

    <label class="lbl" for="died_on"><?= t('died_on') ?></label>
    <input class="in" id="died_on" name="died_on" type="date" value="<?= htmlspecialchars($v['died_on']) ?>">

    <label class="lbl" for="hijri_date"><?= t('hijri_date') ?></label>
    <input class="in" id="hijri_date" name="hijri_date" value="<?= htmlspecialchars($v['hijri_date']) ?>" maxlength="40" placeholder="7 Rajab 1440">

    <label class="lbl" for="section"><?= t('section') ?></label>
    <input class="in" id="section" name="section" value="<?= htmlspecialchars($v['section']) ?>" maxlength="10" required>

    <label class="lbl" for="plot"><?= t('plot') ?></label>
    <input class="in" id="plot" name="plot" value="<?= htmlspecialchars($v['plot']) ?>" maxlength="10" required>

        <label class="lbl" for="grave_no"><?= t('grave_no') ?></label>
    <input class="in" id="grave_no" name="grave_no" value="<?= htmlspecialchars($v['grave_no']) ?>" maxlength="20">
    <label class="lbl" for="person_photo"><?= t('person_photo') ?></label>
    <input class="in" id="person_photo" name="person_photo" type="file" accept="image/*" style="height:auto;padding:12px 16px">
    <label style="display:flex;gap:10px;align-items:flex-start;margin-top:10px;font-size:14px"><input type="checkbox" name="photo_consent" value="1" style="width:22px;height:22px;flex:none"> <span><?= t('photo_consent') ?></span></label>

    <label class="lbl" for="grave_photo"><?= t('grave_photo') ?></label>
    <input class="in" id="grave_photo" name="grave_photo" type="file" accept="image/*" style="height:auto;padding:12px 16px">

    <button class="btn" type="submit"><?= t('save_burial') ?></button>
  </form>
  <?php if ($savedId) { qr_block($savedId); } ?>
</main>
</body>
</html>