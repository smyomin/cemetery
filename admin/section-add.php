<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('block.edit');

$cems = $pdo->query('SELECT id, name_en, name_my FROM cemeteries ORDER BY name_en')->fetchAll();
if (admin_cemetery_ids() !== null) {
    $cems = array_values(array_filter($cems, fn($c) => can_access_cemetery((int)$c['id'])));
}
$allowed = array_map(fn($c) => (int)$c['id'], $cems);
$err = '';
$v = ['cemetery_id' => $allowed[0] ?? 0, 'code' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['cemetery_id'] = (int)($_POST['cemetery_id'] ?? 0);
    $v['code'] = trim($_POST['code'] ?? '');
    $taken = false;
    if (in_array($v['cemetery_id'], $allowed, true) && $v['code'] !== '') {
        $c = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE cemetery_id = :c AND code = :k');
        $c->execute([':c' => $v['cemetery_id'], ':k' => $v['code']]);
        $taken = (int)$c->fetchColumn() > 0;
    }
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (!in_array($v['cemetery_id'], $allowed, true)) {
        $err = 'err_cemetery';
    } elseif ($v['code'] === '' || mb_strlen($v['code']) > 10) {
        $err = 'err_code';
    } elseif ($taken) {
        $err = 'err_code_taken';
    } else {
        $pdo->prepare('INSERT INTO sections (cemetery_id, code) VALUES (:c, :k)')->execute([':c' => $v['cemetery_id'], ':k' => $v['code']]);
        $newId = (int)$pdo->lastInsertId();
        log_activity($pdo, 'block.create', 'block', $newId, $v['cemetery_id'], $v['code']);
        header('Location: /admin/section-edit.php?id=' . $newId . '&updated=1');
        exit;
    }
}
$pageTitle = t('add_block');
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
      <a href="/admin/section-add.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/section-add.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/sections.php">&larr; <?= t('blocks') ?></a>
    <h1 class="ti"><?= t('add_block') ?></h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/section-add.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>
    <label class="lbl" for="cemetery_id"><?= t('cemetery') ?></label>
    <select class="in" id="cemetery_id" name="cemetery_id" required>
      <?php foreach ($cems as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $v['cemetery_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= htmlspecialchars(($lang === 'my' && $c['name_my']) ? $c['name_my'] : $c['name_en']) ?></option><?php endforeach; ?>
    </select>
    <label class="lbl" for="code"><?= t('block_code') ?></label>
    <input class="in" id="code" name="code" value="<?= htmlspecialchars($v['code']) ?>" maxlength="10" required>
    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
</main>
</body>
</html>