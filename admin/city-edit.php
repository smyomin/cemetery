<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('admin.manage');

$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id > 0) {
    $s = $pdo->prepare('SELECT * FROM cities WHERE id = :id');
    $s->execute([':id' => $id]);
    $row = $s->fetch();
    if (!$row) { http_response_code(404); exit(t('not_found')); }
}
$err = '';
$v = ['name_en' => $row['name_en'] ?? '', 'name_my' => $row['name_my'] ?? '', 'slug' => $row['slug'] ?? ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['name_en'] = trim($_POST['name_en'] ?? '');
    $v['name_my'] = trim($_POST['name_my'] ?? '');
    $v['slug'] = $row ? $row['slug'] : strtolower(trim($_POST['slug'] ?? ''));
    $taken = false;
    if (!$row) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM cities WHERE slug = :s');
        $c->execute([':s' => $v['slug']]);
        $taken = (int)$c->fetchColumn() > 0;
    }
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif ($v['name_en'] === '' || mb_strlen($v['name_en']) > 100 || mb_strlen($v['name_my']) > 100) {
        $err = 'err_name';
    } elseif (!$row && !preg_match('/^[a-z0-9-]{2,60}$/', $v['slug'])) {
        $err = 'err_slug';
    } elseif ($taken) {
        $err = 'err_slug_taken';
    } else {
        $my = $v['name_my'] === '' ? null : $v['name_my'];
        if ($row) {
            $pdo->prepare('UPDATE cities SET name_en = :e, name_my = :m WHERE id = :id')->execute([':e' => $v['name_en'], ':m' => $my, ':id' => $id]);
            log_activity($pdo, 'city.update', 'city', $id, null, $v['name_en']);
            header('Location: /admin/city-edit.php?id=' . $id . '&saved=1');
        } else {
            $pdo->prepare('INSERT INTO cities (name_en, name_my, slug) VALUES (:e, :m, :s)')->execute([':e' => $v['name_en'], ':m' => $my, ':s' => $v['slug']]);
            $newId = (int)$pdo->lastInsertId();
            log_activity($pdo, 'city.create', 'city', $newId, null, $v['name_en']);
            header('Location: /admin/city-edit.php?id=' . $newId . '&saved=1');
        }
        exit;
    }
}
$pageTitle = $row ? t('edit_city') : t('add_city');
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
      <a href="/admin/city-edit.php?id=<?= $id ?>&amp;lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/city-edit.php?id=<?= $id ?>&amp;lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/cities.php">&larr; <?= t('cities') ?></a>
    <h1 class="ti"><?= $pageTitle ?></h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/city-edit.php?id=<?= $id ?>">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if (isset($_GET['saved'])): ?><p class="ok"><?= t('changes_saved') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>
    <label class="lbl" for="name_en"><?= t('name_en') ?></label>
    <input class="in" id="name_en" name="name_en" value="<?= htmlspecialchars($v['name_en']) ?>" maxlength="100" required>
    <label class="lbl" for="name_my"><?= t('name_my') ?></label>
    <input class="in" id="name_my" name="name_my" value="<?= htmlspecialchars($v['name_my']) ?>" maxlength="100">
    <label class="lbl" for="slug"><?= t('slug') ?></label>
    <input class="in" id="slug" name="slug" value="<?= htmlspecialchars($v['slug']) ?>" maxlength="60"<?= $row ? ' readonly' : ' required' ?>>
    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
</main>
</body>
</html>