<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
require __DIR__ . '/../includes/photo.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT g.*, m.name_en AS cem_en, m.name_my AS cem_my FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id WHERE g.id = :id');
$stmt->execute([':id' => $id]);
$g = $stmt->fetch();
if (!$g || ($admin['cemetery_id'] !== null && (int)$g['cemetery_id'] !== $admin['cemetery_id'])) {
    http_response_code(404);
    exit(t('not_found'));
}

$err = '';
$v = [
    'name_en' => $g['name_en'],
    'name_my' => $g['name_my'] ?? '',
    'died_on' => $g['died_on'] ?? '',
    'hijri_date' => $g['hijri_date'] ?? '',
    'section' => $g['section'],
    'plot' => $g['plot'],
    'grave_no' => $g['grave_no'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $d = DateTime::createFromFormat('Y-m-d', $v['died_on']);
    if (!csrf_check()) {
        $err = 'err_session';
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
    } elseif ($v['grave_no'] !== '' && grave_no_taken($pdo, (int)$g['cemetery_id'], $v['grave_no'], $id)) {
        $err = 'err_dup';
    } else {
        $u = $pdo->prepare('UPDATE graves SET name_en = :en, name_my = :my, died_on = :d, hijri_date = :h, section = :s, plot = :p, grave_no = :g WHERE id = :id');
        $u->execute([
            ':en' => $v['name_en'],
            ':my' => $v['name_my'] === '' ? null : $v['name_my'],
            ':d' => $v['died_on'] === '' ? null : $v['died_on'],
            ':h' => $v['hijri_date'] === '' ? null : $v['hijri_date'],
            ':s' => $v['section'],
            ':p' => $v['plot'],
            ':g' => $v['grave_no'] === '' ? null : $v['grave_no'],
            ':id' => $id,
        ]);
        $pdo->prepare('INSERT IGNORE INTO sections (cemetery_id, code) VALUES (:c, :s)')->execute([':c' => (int)$g['cemetery_id'], ':s' => $v['section']]);
        $consent = isset($_POST['photo_consent']) ? 1 : 0;
        [$pp, $e1] = apply_photo('person_photo', 'people', $g['person_photo'], isset($_POST['remove_person_photo']));
        [$gp, $e2] = apply_photo('grave_photo', 'graves', $g['grave_photo'], isset($_POST['remove_grave_photo']));
        $pdo->prepare('UPDATE graves SET person_photo = :pp, grave_photo = :gp, photo_consent = :pc WHERE id = :id')->execute([':pp' => $pp, ':gp' => $gp, ':pc' => $consent, ':id' => $id]);
        $q = ($e1 || $e2) ? '&photoerr=1' : '';
        header('Location: /admin/grave-edit.php?id=' . $id . '&updated=1' . $q);
        exit;
    }
}
$cem = ($lang === 'my' && $g['cem_my']) ? $g['cem_my'] : $g['cem_en'];
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('edit_burial') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/grave-edit.php?id=<?= $id ?>&amp;lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/grave-edit.php?id=<?= $id ?>&amp;lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/graves.php">&larr; <?= t('burial_list') ?></a>
    <h1 class="ti"><?= t('edit_burial') ?></h1>
    <p class="su"><?= htmlspecialchars($cem) ?></p>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/grave-edit.php?id=<?= $id ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if (isset($_GET['updated'])): ?><p class="ok"><?= t('changes_saved') ?> <a href="/<?= $lang ?>/grave/<?= $id ?>"><?= t('view_grave') ?></a></p><?php endif; ?>
    <?php if (isset($_GET['photoerr'])): ?><p class="err"><?= t('err_photo') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="name_en"><?= t('name_en') ?></label>
    <input class="in" id="name_en" name="name_en" value="<?= htmlspecialchars($v['name_en']) ?>" maxlength="150" required>

    <label class="lbl" for="name_my"><?= t('name_my') ?></label>
    <input class="in" id="name_my" name="name_my" value="<?= htmlspecialchars($v['name_my']) ?>" maxlength="150">

    <label class="lbl" for="died_on"><?= t('died_on') ?></label>
    <input class="in" id="died_on" name="died_on" type="date" value="<?= htmlspecialchars($v['died_on']) ?>">

    <label class="lbl" for="hijri_date"><?= t('hijri_date') ?></label>
    <input class="in" id="hijri_date" name="hijri_date" value="<?= htmlspecialchars($v['hijri_date']) ?>" maxlength="40">

    <label class="lbl" for="section"><?= t('section') ?></label>
    <input class="in" id="section" name="section" value="<?= htmlspecialchars($v['section']) ?>" maxlength="10" required>

    <label class="lbl" for="plot"><?= t('plot') ?></label>
    <input class="in" id="plot" name="plot" value="<?= htmlspecialchars($v['plot']) ?>" maxlength="10" required>

        <label class="lbl" for="grave_no"><?= t('grave_no') ?></label>
    <input class="in" id="grave_no" name="grave_no" value="<?= htmlspecialchars($v['grave_no']) ?>" maxlength="20">
    <label class="lbl" for="person_photo"><?= t('person_photo') ?></label>
    <?php if ($g['person_photo']): ?>
      <img class="avatar" src="/uploads/people/<?= htmlspecialchars($g['person_photo']) ?>" alt="" style="margin-bottom:8px">
      <label style="display:flex;gap:10px;align-items:center;margin:0 0 10px;font-size:14px"><input type="checkbox" name="remove_person_photo" value="1" style="width:22px;height:22px"> <?= t('remove_photo') ?></label>
    <?php endif; ?>
    <input class="in" id="person_photo" name="person_photo" type="file" accept="image/*" style="height:auto;padding:12px 16px">
    <label style="display:flex;gap:10px;align-items:flex-start;margin-top:10px;font-size:14px"><input type="checkbox" name="photo_consent" value="1"<?= $g['photo_consent'] ? ' checked' : '' ?> style="width:22px;height:22px;flex:none"> <span><?= t('photo_consent') ?></span></label>

    <label class="lbl" for="grave_photo"><?= t('grave_photo') ?></label>
    <?php if ($g['grave_photo']): ?>
      <img class="gphoto" src="/uploads/graves/<?= htmlspecialchars($g['grave_photo']) ?>" alt="" style="margin:0 0 8px;max-height:160px;max-width:100%">
      <label style="display:flex;gap:10px;align-items:center;margin:0 0 10px;font-size:14px"><input type="checkbox" name="remove_grave_photo" value="1" style="width:22px;height:22px"> <?= t('remove_photo') ?></label>
    <?php endif; ?>
    <input class="in" id="grave_photo" name="grave_photo" type="file" accept="image/*" style="height:auto;padding:12px 16px">

    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
</main>
</body>
</html>