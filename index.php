<?php
$pageTitle = 'Myanmar Muslim Cemetery';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <h1 class="ti">Myanmar Muslim Graves</h1>
    <p class="su">မိသားစုဝင်၏ ဂူကို ရှာဖွေပါ</p>
  </div>
</header>
<main class="container">
  <form class="card search-card" action="/search.php" method="get">
    <h2>Find a grave</h2>
    <input class="in" type="search" name="q" placeholder="Search by name" aria-label="Search by name">
    <button class="btn" type="submit">Search</button>
  </form>
</main>
</body>
</html>