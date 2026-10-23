<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$cems = $pdo->query('SELECT id, name_en FROM cemeteries ORDER BY name_en')->fetchAll();
if ($admin['cemetery_id'] !== null) {
    $cems = array_values(array_filter($cems, fn($c) => (int)$c['id'] === $admin['cemetery_id']));
}
$allowed = array_map(fn($c) => (int)$c['id'], $cems);

$err = '';
$v = ['cemetery_id' => $allowed[0] ?? 0, 'name_en' => '', 'name_my' => '', 'died_on' => '', 'hijri_date' => '', 'section' => '', 'plot' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $v['cemetery_id'] = (int)$v['cemetery_id'];
    $d = DateTime::createFromFormat('Y-m-d', $v['died_on']);
    if (!csrf_check()) {
        $err = 'Session expired. Please try again.';
    } elseif (!in_array($v['cemetery_id'], $allowed, true)) {
        $err = 'Choose a cemetery.';
    } elseif ($v['name_en'] === '' || mb_strlen($v['name_en']) > 150 || mb_strlen($v['name_my']) > 150) {
        $err = 'Enter the name in English (up to 150 characters).';
    } elseif ($v['section'] === '' || $v['plot'] === '' || mb_strlen($v['section']) > 10 || mb_strlen($v['plot']) > 10) {
        $err = 'Section and plot are required (up to 10 characters).';
    } elseif ($v['died_on'] !== '' && (!$d || $d->format('Y-m-d') !== $v['died_on'] || $d > new DateTime('today'))) {
        $err = 'Enter a valid date of death that is not in the future.';
    } elseif (mb_strlen($v['hijri_date']) > 40) {
        $err = 'The Hijri date is too long.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO graves (cemetery_id, name_en, name_my, died_on, hijri_date, section, plot) VALUES (:c, :en, :my, :d, :h, :s, :p)');
        $stmt->execute([
            ':c' => $v['cemetery_id'],
            ':en' => $v['name_en'],
            ':my' => $v['name_my'] === '' ? null : $v['name_my'],
            ':d' => $v['died_on'] === '' ? null : $v['died_on'],
            ':h' => $v['hijri_date'] === '' ? null : $v['hijri_date'],
            ':s' => $v['section'],
            ':p' => $v['plot'],
        ]);
        header('Location: /admin/grave-add.php?saved=' . $pdo->lastInsertId());
        exit;
    }
}
$savedId = (int)($_GET['saved'] ?? 0);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Add a burial</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <a class="back" href="/admin/">&larr; Admin</a>
    <h1 class="ti">Add a burial</h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/grave-add.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($savedId): ?><p class="ok">Saved. <a href="/grave.php?id=<?= $savedId ?>">View the grave page</a></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= htmlspecialchars($err) ?></p><?php endif; ?>

    <label class="lbl" for="cemetery_id">Cemetery</label>
    <select class="in" id="cemetery_id" name="cemetery_id">
      <?php foreach ($cems as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $v['cemetery_id'] ? ' selected' : '' ?>><?= htmlspecialchars($c['name_en']) ?></option>
      <?php endforeach; ?>
    </select>

    <label class="lbl" for="name_en">Name (English)</label>
    <input class="in" id="name_en" name="name_en" value="<?= htmlspecialchars($v['name_en']) ?>" maxlength="150" required>

    <label class="lbl" for="name_my">Name (Burmese)</label>
    <input class="in" id="name_my" name="name_my" value="<?= htmlspecialchars($v['name_my']) ?>" maxlength="150">

    <label class="lbl" for="died_on">Date of death</label>
    <input class="in" id="died_on" name="died_on" type="date" value="<?= htmlspecialchars($v['died_on']) ?>">

    <label class="lbl" for="hijri_date">Hijri date</label>
    <input class="in" id="hijri_date" name="hijri_date" value="<?= htmlspecialchars($v['hijri_date']) ?>" maxlength="40" placeholder="7 Rajab 1440">

    <label class="lbl" for="section">Section</label>
    <input class="in" id="section" name="section" value="<?= htmlspecialchars($v['section']) ?>" maxlength="10" required>

    <label class="lbl" for="plot">Plot</label>
    <input class="in" id="plot" name="plot" value="<?= htmlspecialchars($v['plot']) ?>" maxlength="10" required>

    <button class="btn" type="submit">Save burial</button>
  </form>
</main>
</body>
</html>
