<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
require __DIR__ . '/../includes/qr.php';
require __DIR__ . '/../includes/photo.php';
$admin = require_admin();
require_can('burial.edit');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT g.*, m.name_en AS cem_en, m.name_my AS cem_my FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id WHERE g.id = :id');
$stmt->execute([':id' => $id]);
$g = $stmt->fetch();
if (!$g || !can_access_cemetery((int)$g['cemetery_id'])) {
    http_response_code(404);
    exit(t('not_found'));
}
$cemId = (int)$g['cemetery_id'];
$sq = $pdo->prepare('SELECT code FROM sections WHERE cemetery_id = :c ORDER BY code');
$sq->execute([':c' => $cemId]);
$codes = $sq->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($g['section'], $codes, true)) { $codes[] = $g['section']; }

$fields = ['name_en', 'name_my', 'name_ar', 'father_name_en', 'father_name_my', 'father_name_ar', 'gender', 'age', 'contact_name', 'contact_phone', 'note', 'died_on', 'hijri_date', 'section', 'plot', 'grave_no'];
$v = [];
foreach ($fields as $k) { $v[$k] = (string)($g[$k] ?? ''); }
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $k) { $v[$k] = trim($_POST[$k] ?? ''); }
    $tooLong = mb_strlen($v['contact_name']) > 100 || mb_strlen($v['note']) > 2000;
    foreach (['name_en', 'name_my', 'name_ar', 'father_name_en', 'father_name_my', 'father_name_ar'] as $k) {
        if (mb_strlen($v[$k]) > 150) { $tooLong = true; }
    }
    $secOk = in_array($v['section'], $codes, true);
    $d = DateTime::createFromFormat('Y-m-d', $v['died_on']);
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif ($v['name_en'] === '') {
        $err = 'err_name';
    } elseif ($tooLong) {
        $err = 'err_len';
    } elseif (!$secOk) {
        $err = 'err_section';
    } elseif ($v['plot'] === '' || mb_strlen($v['plot']) > 10) {
        $err = 'err_secplot';
    } elseif ($v['died_on'] !== '' && (!$d || $d->format('Y-m-d') !== $v['died_on'] || $d > new DateTime('today'))) {
        $err = 'err_date';
    } elseif (mb_strlen($v['hijri_date']) > 40) {
        $err = 'err_hijri';
    } elseif (mb_strlen($v['grave_no']) > 20) {
        $err = 'err_grave_no';
    } elseif (!in_array($v['gender'], ['', 'male', 'female'], true)) {
        $err = 'err_gender';
    } elseif ($v['age'] !== '' && (!ctype_digit($v['age']) || (int)$v['age'] > 130)) {
        $err = 'err_age';
    } elseif ($v['contact_phone'] !== '' && !preg_match('/^[0-9+()\s-]{5,30}$/', $v['contact_phone'])) {
        $err = 'err_phone';
    } elseif ($v['grave_no'] !== '' && grave_no_taken($pdo, $cemId, $v['grave_no'], $id)) {
        $err = 'err_dup';
    } else {
        $nul = fn($x) => $x === '' ? null : $x;
        $consent = isset($_POST['photo_consent']) ? 1 : 0;
        [$pp, $e1] = apply_photo('person_photo', 'people', $g['person_photo'], isset($_POST['remove_person_photo']));
        [$gp, $e2] = apply_photo('grave_photo', 'graves', $g['grave_photo'], isset($_POST['remove_grave_photo']));
        $pdo->prepare('UPDATE graves SET name_en = :en, name_my = :my, name_ar = :ar, father_name_en = :fen, father_name_my = :fmy, father_name_ar = :far, gender = :g, age = :age, contact_name = :cn, contact_phone = :cp, note = :note, died_on = :d, hijri_date = :h, section = :s, plot = :p, grave_no = :gn, person_photo = :pp, grave_photo = :gp, photo_consent = :pc WHERE id = :id')
            ->execute([
                ':en' => $v['name_en'], ':my' => $nul($v['name_my']), ':ar' => $nul($v['name_ar']),
                ':fen' => $nul($v['father_name_en']), ':fmy' => $nul($v['father_name_my']), ':far' => $nul($v['father_name_ar']),
                ':g' => $nul($v['gender']), ':age' => $v['age'] === '' ? null : (int)$v['age'],
                ':cn' => $nul($v['contact_name']), ':cp' => $nul($v['contact_phone']), ':note' => $nul($v['note']),
                ':d' => $nul($v['died_on']), ':h' => $nul($v['hijri_date']), ':s' => $v['section'], ':p' => $v['plot'], ':gn' => $nul($v['grave_no']),
                ':pp' => $pp, ':gp' => $gp, ':pc' => $consent, ':id' => $id,
            ]);
        log_activity($pdo, 'burial.update', 'burial', $id, $cemId, $v['name_en']);
        header('Location: /admin/grave-edit.php?id=' . $id . '&updated=1' . (($e1 || $e2) ? '&photoerr=1' : ''));
        exit;
    }
}
$cem = ($lang === 'my' && $g['cem_my']) ? $g['cem_my'] : $g['cem_en'];
$pageTitle = t('edit_burial');
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
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/grave-edit.php?id=<?= $id ?>&amp;lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/grave-edit.php?id=<?= $id ?>&amp;lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/graves.php">&larr; <?= t('burial_list') ?></a>
    <h1 class="ti"><?= $pageTitle ?></h1>
    <p class="su"><?= htmlspecialchars($cem) ?></p>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/grave-edit.php?id=<?= $id ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if (isset($_GET['updated'])): ?><p class="ok"><?= t('changes_saved') ?> <a href="/<?= $lang ?>/grave/<?= $id ?>"><?= t('view_grave') ?></a></p><?php endif; ?>
    <?php if (isset($_GET['photoerr'])): ?><p class="err"><?= t('err_photo') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="section"><?= t('section') ?></label>
    <select class="in" id="section" name="section">
      <?php foreach ($codes as $c): ?><option value="<?= htmlspecialchars($c) ?>"<?= $v['section'] === $c ? ' selected' : '' ?>><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
    </select>

    <label class="lbl" for="plot"><?= t('plot') ?></label>
    <input class="in" id="plot" name="plot" value="<?= htmlspecialchars($v['plot']) ?>" maxlength="10" required>

    <label class="lbl" for="grave_no"><?= t('grave_no') ?></label>
    <input class="in" id="grave_no" name="grave_no" value="<?= htmlspecialchars($v['grave_no']) ?>" maxlength="20">

    <label class="lbl" for="name_en"><?= t('name_en') ?></label>
    <input class="in" id="name_en" name="name_en" value="<?= htmlspecialchars($v['name_en']) ?>" maxlength="150" required>

    <label class="lbl" for="name_my"><?= t('name_my') ?></label>
    <input class="in" id="name_my" name="name_my" value="<?= htmlspecialchars($v['name_my']) ?>" maxlength="150">

    <label class="lbl" for="name_ar"><?= t('name_ar') ?></label>
    <input class="in" id="name_ar" name="name_ar" value="<?= htmlspecialchars($v['name_ar']) ?>" maxlength="150" dir="rtl" lang="ar">

    <label class="lbl" for="father_name_en"><?= t('father_en') ?></label>
    <input class="in" id="father_name_en" name="father_name_en" value="<?= htmlspecialchars($v['father_name_en']) ?>" maxlength="150">

    <label class="lbl" for="father_name_my"><?= t('father_my') ?></label>
    <input class="in" id="father_name_my" name="father_name_my" value="<?= htmlspecialchars($v['father_name_my']) ?>" maxlength="150">

    <label class="lbl" for="father_name_ar"><?= t('father_ar') ?></label>
    <input class="in" id="father_name_ar" name="father_name_ar" value="<?= htmlspecialchars($v['father_name_ar']) ?>" maxlength="150" dir="rtl" lang="ar">

    <label class="lbl" for="gender"><?= t('gender') ?></label>
    <select class="in" id="gender" name="gender">
      <option value=""><?= t('not_recorded') ?></option>
      <option value="male"<?= $v['gender'] === 'male' ? ' selected' : '' ?>><?= t('male') ?></option>
      <option value="female"<?= $v['gender'] === 'female' ? ' selected' : '' ?>><?= t('female') ?></option>
    </select>

    <label class="lbl" for="age"><?= t('age') ?></label>
    <input class="in" id="age" name="age" type="number" min="0" max="130" inputmode="numeric" value="<?= htmlspecialchars($v['age']) ?>">

    <label class="lbl" for="died_on"><?= t('died_on') ?></label>
    <input class="in" id="died_on" name="died_on" type="date" value="<?= htmlspecialchars($v['died_on']) ?>">

    <label class="lbl" for="hijri_date"><?= t('hijri_date') ?></label>
    <input class="in" id="hijri_date" name="hijri_date" value="<?= htmlspecialchars($v['hijri_date']) ?>" maxlength="40">

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

    <h2 class="loc-h" style="margin:24px 0 0"><?= t('private_group') ?></h2>

    <label class="lbl" for="contact_name"><?= t('contact_name') ?></label>
    <input class="in" id="contact_name" name="contact_name" value="<?= htmlspecialchars($v['contact_name']) ?>" maxlength="100">

    <label class="lbl" for="contact_phone"><?= t('contact_phone') ?></label>
    <input class="in" id="contact_phone" name="contact_phone" type="tel" value="<?= htmlspecialchars($v['contact_phone']) ?>" maxlength="30">

    <label class="lbl" for="note"><?= t('note') ?></label>
    <textarea class="in" id="note" name="note" rows="4" maxlength="2000" style="height:auto;padding:12px 16px"><?= htmlspecialchars($v['note']) ?></textarea>

    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
  <?php qr_block($id); ?>
  <?php if (can('burial.delete')): ?>
  <form class="card" method="post" action="/admin/grave-delete.php" style="margin:14px 20px 24px;padding:16px" onsubmit="return confirm(<?= htmlspecialchars(json_encode($STRINGS['confirm_delete']), ENT_QUOTES) ?>)">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn" type="submit" style="margin-top:0;background:#8A2D22"><?= t('delete_burial') ?></button>
  </form>
  <?php endif; ?>
</main>
</body>
</html>