<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <h1 class="ti">Admin</h1>
    <p class="su">Logged in as <?= htmlspecialchars($admin['username']) ?></p>
  </div>
</header>
<main class="container">
  <div class="card search-card">
    <p>Login works. The burial form comes next.</p>
    <a class="btn" href="/logout.php" style="text-align:center;line-height:54px;text-decoration:none">Log out</a>
  </div>
</main>
</body>
</html>
