<?php
require __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['admin'])) {
    header('Location: /admin/');
    exit;
}

$error = '';
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
    $error = 'Wrong username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Admin login</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <h1 class="ti">Admin login</h1>
    <p class="su">Myanmar Muslim Graves</p>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/login.php">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if ($error): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <input class="in" type="text" name="username" placeholder="Username" autocomplete="username" aria-label="Username" required>
    <input class="in" type="password" name="password" placeholder="Password" autocomplete="current-password" aria-label="Password" required style="margin-top:12px">
    <button class="btn" type="submit">Log in</button>
  </form>
</main>
</body>
</html>
