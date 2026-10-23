<?php
require __DIR__ . '/includes/config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT g.*, m.name_en AS cemetery
     FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id
     WHERE g.id = :id'
);
$stmt->execute([':id' => $id]);
$g = $stmt->fetch();
if (!$g) {
    http_response_code(404);
}
$pageTitle = $g ? $g['name_en'] : 'Grave not found';
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
    <a class="back" href="/">&larr; Home</a>
    <h1 class="ti"><?= htmlspecialchars($pageTitle) ?></h1>
    <?php if ($g && $g['name_my']): ?><p class="su"><?= htmlspecialchars($g['name_my']) ?></p><?php endif; ?>
  </div>
</header>
<main class="container">
<?php if (!$g): ?>
  <p class="empty">This grave could not be found.</p>
<?php else: ?>
  <div class="card grave-card">
    <?php if ($g['died_on']): ?><div class="drow"><span>Died</span><b><?= date('j F Y', strtotime($g['died_on'])) ?></b></div><?php endif; ?>
    <?php if ($g['hijri_date']): ?><div class="drow"><span>Hijri</span><b><?= htmlspecialchars($g['hijri_date']) ?></b></div><?php endif; ?>
    <div class="drow"><span>Cemetery</span><b><?= htmlspecialchars($g['cemetery']) ?></b></div>
    <div class="drow"><span>Section</span><b><?= htmlspecialchars($g['section']) ?></b></div>
    <div class="drow"><span>Plot</span><b><?= htmlspecialchars($g['plot']) ?></b></div>
  </div>
<?php endif; ?>
</main>
</body>
</html>