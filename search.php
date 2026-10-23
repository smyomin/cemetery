<?php
require __DIR__ . '/includes/config.php';

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $stmt = $pdo->prepare(
        'SELECT g.id, g.name_en, g.name_my, g.died_on, g.section, g.plot, m.name_en AS cemetery
         FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id
         WHERE g.name_en LIKE :q1 OR g.name_my LIKE :q2
         ORDER BY g.name_en LIMIT 50'
    );
    $stmt->execute([':q1' => $like, ':q2' => $like]);
    $results = $stmt->fetchAll();
}

function initials(string $name): string {
    $name = preg_replace('/^(U|Daw|Ko|Ma|Mg|Maung)\s+/i', '', $name);
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)), 0, 2) as $w) {
        $out .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $out;
}

$pageTitle = 'Search results';
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
<header class="hero hero-sm">
  <div class="container">
    <a class="back" href="/">&larr; Home</a>
    <h1 class="ti">Results</h1>
    <p class="su"><?= count($results) ?> found</p>
    <form action="/search.php" method="get">
      <input class="in" type="search" name="q" value="<?= htmlspecialchars($q) ?>" aria-label="Search by name">
    </form>
  </div>
</header>
<main class="container">
<?php if ($q === ''): ?>
  <p class="empty">Type a name to search.</p>
<?php elseif (!$results): ?>
  <p class="empty">No graves found for "<?= htmlspecialchars($q) ?>".</p>
<?php else: ?>
  <div class="list">
  <?php foreach ($results as $r): ?>
    <a class="card res" href="/grave.php?id=<?= (int)$r['id'] ?>">
      <span class="av"><?= htmlspecialchars(initials($r['name_en'])) ?></span>
      <span class="grow">
        <b><?= htmlspecialchars($r['name_en']) ?></b><br>
        <span class="sm"><?= htmlspecialchars($r['name_my'] ?? '') ?> · Section <?= htmlspecialchars($r['section']) ?>, Plot <?= htmlspecialchars($r['plot']) ?></span>
      </span>
      <?php if ($r['died_on']): ?><span class="yr"><?= date('Y', strtotime($r['died_on'])) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>