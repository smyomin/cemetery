<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
require __DIR__ . '/../includes/qr.php';
require __DIR__ . '/../includes/photo.php';
$admin = require_admin();
require_can('burial.create');

$cems = $pdo->query('SELECT id, name_en, name_my FROM cemeteries ORDER BY name_en')->fetchAll();
$cems = array_values(array_filter($cems, fn($c) => can_access_cemetery((int)$c['id'])));
$allowed = array_map(fn($c) => (int)$c['id'], $cems);

$sp = [];
$sq = $pdo->prepare('SELECT cemetery_id AS c, code AS k FROM sections WHERE ' . cemetery_scope_sql('cemetery_id', $sp) . ' ORDER BY code');
$sq->execute($sp);
$sections = $sq->fetchAll();

$fields = ['name_en', 'name_my', 'name_ar', 'father_name_en', 'father_name_my', 'father_name_ar', 'home_city_en', 'home_city_my', 'gender', 'age', 'contact_name', 'contact_phone', 'note', 'died_on', 'hijri_date', 'section', 'plot', 'grave_no'];
$v = array_fill_keys($fields, '');
$v['cemetery_id'] = $allowed[0] ?? 0;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $k) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $v['cemetery_id'] = (int)($_POST['cemetery_id'] ?? 0);
    $tooLong = mb_strlen($v['contact_name']) > 100 || mb_strlen($v['note']) > 2000;
    foreach (['name_en', 'name_my', 'name_ar', 'father_name_en', 'father_name_my', 'father_name_ar', 'home_city_en', 'home_city_my'] as $k) {
        if (mb_strlen($v[$k]) > 150) { $tooLong = true; }
    }
    $secOk = false;
    if (in_array($v['cemetery_id'], $allowed, true) && $v['section'] !== '') {
        $c = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE cemetery_id = :c AND code = :k');
        $c->execute([':c' => $v['cemetery_id'], ':k' => $v['section']]);
        $secOk = (int)$c->fetchColumn() > 0;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v['died_on']);
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (!in_array($v['cemetery_id'], $allowed, true)) {
        $err = 'err_cemetery';
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
    } elseif ($v['grave_no'] !== '' && grave_no_taken($pdo, $v['cemetery_id'], $v['grave_no'], 0)) {
        $err = 'err_dup';
    } else {
        $nul = fn($x) => $x === '' ? null : $x;
        $stmt = $pdo->prepare('INSERT INTO graves (cemetery_id, name_en, name_my, name_ar, father_name_en, father_name_my, father_name_ar, home_city_en, home_city_my, gender, age, contact_name, contact_phone, note, died_on, hijri_date, section, plot, grave_no) VALUES (:c, :en, :my, :ar, :fen, :fmy, :far, :hce, :hcm, :g, :age, :cn, :cp, :note, :d, :h, :s, :p, :gn)');
        $stmt->execute([
            ':c' => $v['cemetery_id'], ':en' => $v['name_en'], ':my' => $nul($v['name_my']), ':ar' => $nul($v['name_ar']),
            ':fen' => $nul($v['father_name_en']), ':fmy' => $nul($v['father_name_my']), ':far' => $nul($v['father_name_ar']),
            ':hce' => $nul($v['home_city_en']), ':hcm' => $nul($v['home_city_my']), ':g' => $nul($v['gender']), ':age' => $v['age'] === '' ? null : (int)$v['age'],
            ':cn' => $nul($v['contact_name']), ':cp' => $nul($v['contact_phone']), ':note' => $nul($v['note']),
            ':d' => $nul($v['died_on']), ':h' => $nul($v['hijri_date']), ':s' => $v['section'], ':p' => $v['plot'], ':gn' => $nul($v['grave_no']),
        ]);
        $newId = (int)$pdo->lastInsertId();
        log_activity($pdo, 'burial.create', 'burial', $newId, $v['cemetery_id'], $v['name_en']);
        $consent = isset($_POST['photo_consent']) ? 1 : 0;
        [$pp, $e1] = save_photo($_FILES['person_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'people');
        [$gp, $e2] = save_photo($_FILES['grave_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'graves');
        $pdo->prepare('UPDATE graves SET person_photo = :pp, grave_photo = :gp, photo_consent = :pc WHERE id = :id')->execute([':pp' => $pp, ':gp' => $gp, ':pc' => $consent, ':id' => $newId]);
        $q = ($e1 || $e2) ? '&photoerr=1' : '';
        header('Location: /admin/grave-add.php?saved=' . $newId . $q);
        exit;
    }
}
$savedId = (int)($_GET['saved'] ?? 0);
$pageTitle = t('add_burial');
$J = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
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
      <a href="/admin/grave-add.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/grave-add.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= $pageTitle ?></h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/grave-add.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($savedId): ?><p class="ok"><?= t('saved') ?> <a href="/<?= $lang ?>/grave/<?= $savedId ?>"><?= t('view_grave') ?></a></p><?php endif; ?>
    <?php if (isset($_GET['photoerr'])): ?><p class="err"><?= t('err_photo') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="cemetery_id"><?= t('cemetery') ?></label>
    <select class="in" id="cemetery_id" name="cemetery_id" required>
      <?php foreach ($cems as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $v['cemetery_id'] ? ' selected' : '' ?>><?= htmlspecialchars(($lang === 'my' && $c['name_my']) ? $c['name_my'] : $c['name_en']) ?></option><?php endforeach; ?>
    </select>

    <label class="lbl" for="section"><?= t('section') ?></label>
    <select class="in" id="section" name="section" required></select>
    <p class="sm" id="nosec" style="display:none;margin:8px 0 0"><?= t('no_blocks_hint') ?></p>

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
    <label class="lbl" for="home_city_en"><?= t('home_city_en') ?></label>
    <input class="in" id="home_city_en" name="home_city_en" value="<?= htmlspecialchars($v['home_city_en']) ?>" maxlength="150">
    <p class="sm" style="margin:6px 0 0"><?= t('home_city_hint') ?></p>

    <label class="lbl" for="home_city_my"><?= t('home_city_my') ?></label>
    <input class="in" id="home_city_my" name="home_city_my" value="<?= htmlspecialchars($v['home_city_my']) ?>" maxlength="150">

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
    <input class="in" id="hijri_date" name="hijri_date" value="<?= htmlspecialchars($v['hijri_date']) ?>" maxlength="40" placeholder="7 Rajab 1440">

    <label class="lbl" for="person_photo"><?= t('person_photo') ?></label>
    <input class="in" id="person_photo" name="person_photo" type="file" accept="image/*" style="height:auto;padding:12px 16px">
    <label style="display:flex;gap:10px;align-items:flex-start;margin-top:10px;font-size:14px"><input type="checkbox" name="photo_consent" value="1" style="width:22px;height:22px;flex:none"> <span><?= t('photo_consent') ?></span></label>

    <label class="lbl" for="grave_photo"><?= t('grave_photo') ?></label>
    <input class="in" id="grave_photo" name="grave_photo" type="file" accept="image/*" style="height:auto;padding:12px 16px">

    <h2 class="loc-h" style="margin:24px 0 0"><?= t('private_group') ?></h2>

    <label class="lbl" for="contact_name"><?= t('contact_name') ?></label>
    <input class="in" id="contact_name" name="contact_name" value="<?= htmlspecialchars($v['contact_name']) ?>" maxlength="100">

    <label class="lbl" for="contact_phone"><?= t('contact_phone') ?></label>
    <input class="in" id="contact_phone" name="contact_phone" type="tel" value="<?= htmlspecialchars($v['contact_phone']) ?>" maxlength="30">

    <label class="lbl" for="note"><?= t('note') ?></label>
    <textarea class="in" id="note" name="note" rows="4" maxlength="2000" style="height:auto;padding:12px 16px"><?= htmlspecialchars($v['note']) ?></textarea>

    <button class="btn" type="submit"><?= t('save_burial') ?></button>
  </form>
  <?php if ($savedId) { qr_block($savedId); } ?>
</main>
<script>
(function () {
  var S = <?= json_encode($sections, $J) ?>;
  var cem = document.getElementById('cemetery_id');
  var sec = document.getElementById('section');
  var wanted = <?= json_encode($v['section'], $J) ?>;
  var choose = <?= json_encode($STRINGS['choose_cemetery'], $J) ?>;
  function fill() {
    sec.innerHTML = '';
    var o = document.createElement('option');
    o.value = '';
    o.textContent = choose;
    sec.appendChild(o);
    var n = 0;
    S.forEach(function (x) {
      if (String(x.c) === cem.value) {
        var p = document.createElement('option');
        p.value = x.k;
        p.textContent = x.k;
        if (x.k === wanted) { p.selected = true; }
        sec.appendChild(p);
        n++;
      }
    });
    document.getElementById('nosec').style.display = n ? 'none' : 'block';
  }
  cem.addEventListener('change', function () { wanted = ''; fill(); });
  fill();
})();
</script>
</body>
</html>