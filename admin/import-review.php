<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('burial.create');

$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
$s = $pdo->prepare("SELECT * FROM fb_import WHERE fb_post_id = :id AND import_status = 'new'");
$s->execute([':id' => $id]);
$row = $s->fetch();
$cemId = $row ? (int)$row['cemetery_id'] : 0;
if (!$row || $cemId < 1 || !can_access_cemetery($cemId)) {
    http_response_code(404);
    exit(t('not_found'));
}
$cq = $pdo->prepare('SELECT name_en, name_my FROM cemeteries WHERE id = :id');
$cq->execute([':id' => $cemId]);
$cem = $cq->fetch();
$cemName = ($lang === 'my' && $cem['name_my']) ? $cem['name_my'] : $cem['name_en'];
$sq = $pdo->prepare('SELECT code FROM sections WHERE cemetery_id = :c ORDER BY code');
$sq->execute([':c' => $cemId]);
$codes = $sq->fetchAll(PDO::FETCH_COLUMN);

$str = fn($k) => (string)($row[$k] ?? '');
$note = trim($str('note_my') . ($str('alias_my') !== '' ? "\nAlso known as: " . $str('alias_my') : '') . "\nImported from the Facebook post of " . substr($str('posted_at'), 0, 10) . '.');
$fields = ['name_en', 'name_my', 'name_community_my', 'name_ar', 'father_name_en', 'father_name_my', 'father_name_ar', 'home_city_en', 'home_city_my', 'gender', 'age', 'died_on', 'hijri_date', 'section', 'plot', 'grave_no', 'note'];
$v = array_fill_keys($fields, '');
foreach (['name_en', 'name_my', 'name_community_my', 'father_name_my', 'home_city_en', 'home_city_my', 'gender', 'age', 'died_on'] as $k) { $v[$k] = $str($k); }
$v['note'] = $note;
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (($_POST['action'] ?? '') === 'skip') {
        $u = $pdo->prepare("UPDATE fb_import SET import_status = 'skip' WHERE fb_post_id = :id AND import_status = 'new'");
        $u->execute([':id' => $id]);
        log_activity($pdo, 'import.skip', 'import', null, $cemId, $str('name_my'));
        header('Location: /admin/import.php?done=skipped');
        exit;
    } else {
        foreach ($fields as $k) { $v[$k] = trim($_POST[$k] ?? ''); }
        $tooLong = mb_strlen($v['note']) > 2000;
        foreach (['name_en', 'name_my', 'name_ar', 'father_name_en', 'father_name_my', 'father_name_ar', 'home_city_en', 'home_city_my'] as $k) {
            if (mb_strlen($v[$k]) > 150) { $tooLong = true; }
        }
        if (mb_strlen($v['name_community_my']) > 200) { $tooLong = true; }
        $d = DateTime::createFromFormat('Y-m-d', $v['died_on']);
        if ($v['name_en'] === '') {
            $err = 'err_name';
        } elseif ($tooLong) {
            $err = 'err_len';
        } elseif (!in_array($v['section'], $codes, true)) {
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
        } elseif ($v['grave_no'] !== '' && grave_no_taken($pdo, $cemId, $v['grave_no'], 0)) {
            $err = 'err_dup';
        } else {
            $nul = fn($x) => $x === '' ? null : $x;
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO graves (cemetery_id, name_en, name_my, name_community_my, name_ar, father_name_en, father_name_my, father_name_ar, home_city_en, home_city_my, gender, age, note, died_on, hijri_date, section, plot, grave_no) VALUES (:c, :en, :my, :cm, :ar, :fen, :fmy, :far, :hce, :hcm, :g, :age, :note, :d, :h, :s, :p, :gn)')
                    ->execute([
                        ':c' => $cemId, ':en' => $v['name_en'], ':my' => $nul($v['name_my']), ':cm' => $nul($v['name_community_my']), ':ar' => $nul($v['name_ar']),
                        ':fen' => $nul($v['father_name_en']), ':fmy' => $nul($v['father_name_my']), ':far' => $nul($v['father_name_ar']),
                        ':hce' => $nul($v['home_city_en']), ':hcm' => $nul($v['home_city_my']),
                        ':g' => $nul($v['gender']), ':age' => $v['age'] === '' ? null : (int)$v['age'], ':note' => $nul($v['note']),
                        ':d' => $nul($v['died_on']), ':h' => $nul($v['hijri_date']), ':s' => $v['section'], ':p' => $v['plot'], ':gn' => $nul($v['grave_no']),
                    ]);
                $newId = (int)$pdo->lastInsertId();
                $mk = $pdo->prepare("UPDATE fb_import SET import_status = 'imported', graves_id = :g WHERE fb_post_id = :id AND import_status = 'new'");
                $mk->execute([':g' => $newId, ':id' => $id]);
                if ($mk->rowCount() !== 1) { throw new RuntimeException('already imported'); }
                $pdo->commit();
                log_activity($pdo, 'burial.create', 'burial', $newId, $cemId, $v['name_en'] . ' (from Facebook)');
                header('Location: /admin/import.php?done=saved');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $err = 'err_save';
            }
        }
    }
}

// similar burials that already exist
$sim = $pdo->prepare('SELECT id, name_en, name_my, died_on, section, plot FROM graves
    WHERE cemetery_id = :c AND ((:nm1 <> \'\' AND name_my = :nm2) OR (:cm1 <> \'\' AND name_community_my = :cm2) OR (:en1 <> \'\' AND name_en = :en2)) LIMIT 5');
$sim->execute([':c' => $cemId, ':nm1' => $v['name_my'], ':nm2' => $v['name_my'], ':cm1' => $v['name_community_my'], ':cm2' => $v['name_community_my'], ':en1' => $v['name_en'], ':en2' => $v['name_en']]);
$similar = $sim->fetchAll();
$flags = $str('review_flags') === '' ? [] : explode(' ; ', $str('review_flags'));
$pageTitle = t('import_review');
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
      <a href="/admin/import-review.php?id=<?= urlencode($id) ?>&amp;lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/import-review.php?id=<?= urlencode($id) ?>&amp;lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/import.php">&larr; <?= t('import_back') ?></a>
    <h1 class="ti"><?= t('import_review') ?></h1>
    <p class="su"><?= htmlspecialchars($cemName) ?></p>
  </div>
</header>
<main class="container">
  <div class="card search-card">
    <h2 class="loc-h"><?= t('import_original') ?></h2>
    <p class="post"><?= nl2br(htmlspecialchars(str_replace(' | ', "\n", $str('post_text')))) ?></p>
    <p class="sm" style="margin:8px 0 0"><a href="<?= htmlspecialchars($str('post_url')) ?>" target="_blank" rel="noopener"><?= t('import_open_fb') ?></a> &middot; <?= htmlspecialchars(substr($str('posted_at'), 0, 10)) ?></p>
  </div>

  <?php if ($flags): ?>
  <div class="warn" style="margin:14px 20px 0"><b><?= t('import_flags') ?></b><br><?= nl2br(htmlspecialchars(implode("\n", $flags))) ?></div>
  <?php endif; ?>
  <?php if ($similar): ?>
  <div class="warn" style="margin:14px 20px 0"><b><?= t('import_similar') ?></b>
    <?php foreach ($similar as $x): ?><br><a href="/admin/grave-edit.php?id=<?= (int)$x['id'] ?>"><?= htmlspecialchars($x['name_my'] ?: $x['name_en']) ?></a> &middot; <?= htmlspecialchars((string)$x['died_on']) ?> &middot; <?= htmlspecialchars($x['section'] . '/' . $x['plot']) ?><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form class="card" method="post" action="/admin/import-review.php" style="margin:14px 20px 0;padding:16px">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="section"><?= t('section') ?></label>
    <select class="in" id="section" name="section" required>
      <option value=""><?= t('choose_cemetery') ?></option>
      <?php foreach ($codes as $c): ?><option value="<?= htmlspecialchars($c) ?>"<?= $v['section'] === $c ? ' selected' : '' ?>><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
    </select>
    <?php if (!$codes): ?><p class="sm" style="margin:8px 0 0"><?= t('no_blocks_hint') ?></p><?php endif; ?>

    <label class="lbl" for="plot"><?= t('plot') ?></label>
    <input class="in" id="plot" name="plot" value="<?= htmlspecialchars($v['plot']) ?>" maxlength="10" required>

    <label class="lbl" for="grave_no"><?= t('grave_no') ?></label>
    <input class="in" id="grave_no" name="grave_no" value="<?= htmlspecialchars($v['grave_no']) ?>" maxlength="20">

    <label class="lbl" for="name_en"><?= t('name_en') ?></label>
    <input class="in" id="name_en" name="name_en" value="<?= htmlspecialchars($v['name_en']) ?>" maxlength="150" required>

    <label class="lbl" for="name_my"><?= t('name_my') ?></label>
    <input class="in" id="name_my" name="name_my" value="<?= htmlspecialchars($v['name_my']) ?>" maxlength="150">

    <label class="lbl" for="name_community_my"><?= t('name_community_my') ?></label>
    <input class="in" id="name_community_my" name="name_community_my" value="<?= htmlspecialchars($v['name_community_my']) ?>" maxlength="200">

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
    <input class="in" id="hijri_date" name="hijri_date" value="<?= htmlspecialchars($v['hijri_date']) ?>" maxlength="40">

    <label class="lbl" for="note"><?= t('note') ?></label>
    <textarea class="in" id="note" name="note" rows="4" maxlength="2000" style="height:auto;padding:12px 16px"><?= htmlspecialchars($v['note']) ?></textarea>
    <p class="sm" style="margin:6px 0 0"><?= t('import_note_hint') ?></p>

    <button class="btn" type="submit" name="action" value="save"><?= t('import_save') ?></button>
  </form>

  <form class="card" method="post" action="/admin/import-review.php" style="margin:14px 20px 24px;padding:16px">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
    <button class="btn" type="submit" name="action" value="skip" style="margin-top:0;background:#fff;color:#8A2D22;border:1.5px solid #8A2D22"><?= t('import_skip') ?></button>
  </form>
</main>
</body>
</html>