<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $rep = $_POST['repeat'] ?? '';
    $s = $pdo->prepare('SELECT password_hash FROM admins WHERE id = :id');
    $s->execute([':id' => $admin['id']]);
    $hash = (string)$s->fetchColumn();
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (!password_verify($cur, $hash)) {
        usleep(600000);
        $err = 'err_pw_current';
    } elseif (mb_strlen($new) < 10) {
        $err = 'err_password';
    } elseif ($new !== $rep) {
        $err = 'err_pw_match';
    } else {
        $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id')
            ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $admin['id']]);
        session_regenerate_id(true);
        header('Location: /admin/password.php?done=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('pw_change') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/password.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/password.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('pw_change') ?></h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/password.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if (isset($_GET['done'])): ?><p class="ok"><?= t('pw_changed') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="current"><?= t('pw_current') ?></label>
    <input class="in" id="current" name="current" type="password" maxlength="72" autocomplete="current-password" required>

    <label class="lbl" for="new"><?= t('pw_new') ?></label>
    <input class="in" id="new" name="new" type="password" maxlength="72" autocomplete="new-password" required>

    <label class="lbl" for="repeat"><?= t('pw_confirm') ?></label>
    <input class="in" id="repeat" name="repeat" type="password" maxlength="72" autocomplete="new-password" required>

    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
</main>
</body>
</html>