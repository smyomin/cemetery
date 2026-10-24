<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/lang.php';

if (!empty($_SESSION['admin'])) {
    header('Location: /admin/');
    exit;
}

$error = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $row = false;
    if (csrf_check() && $user !== '') {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, cemetery_id FROM admins WHERE username = :u');
        $stmt->execute([':u' => $user]);
        $row = $stmt->fetch();
    }
    if ($row && password_verify($pass, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'cemetery_id' => $row['cemetery_id'] === null ? null : (int)$row['cemetery_id'],
        ];
        header('Location: /admin/');
        exit;
    }
    usleep(600000);
    $error = true;
}
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('admin_login') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/login.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/login.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <h1 class="ti"><?= t('admin_login') ?></h1>
    <p class="su"><?= t('site_name') ?></p>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/login.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($error): ?><p class="err"><?= t('wrong_login') ?></p><?php endif; ?>
    <input class="in" type="text" name="username" placeholder="<?= t('username') ?>" autocomplete="username" aria-label="<?= t('username') ?>" required>
    <input class="in" type="password" name="password" placeholder="<?= t('password') ?>" autocomplete="current-password" aria-label="<?= t('password') ?>" required style="margin-top:12px">
    <button class="btn" type="submit"><?= t('login_button') ?></button>
  </form>
</main>
</body>
</html>