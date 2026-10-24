<?php
require __DIR__ . '/includes/lang.php';
$slugs = ['about', 'janazah-guide', 'rules-and-hours', 'contact'];
$slug = $_GET['slug'] ?? '';
$file = null;
if (in_array($slug, $slugs, true)) {
    foreach ([$lang, 'en'] as $l) {
        $f = __DIR__ . '/includes/pages/' . $slug . '.' . $l . '.php';
        if (is_file($f)) { $file = $f; break; }
    }
}
$title = 'Page not found';
$content = '<p>This page could not be found.</p>';
if ($file) {
    ob_start();
    include $file;
    $content = ob_get_clean();
} else {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/en/page/<?= htmlspecialchars($slug) ?>"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/my/page/<?= htmlspecialchars($slug) ?>"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/<?= $lang ?>/">&larr; Home</a>
    <h1 class="ti"><?= htmlspecialchars($title) ?></h1>
  </div>
</header>
<main class="container">
  <div class="card search-card page"><?= $content ?></div>
</main>
</body>
</html>
