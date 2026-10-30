<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/lang.php';

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $stmt = $pdo->prepare(
        'SELECT g.id, g.name_en, g.name_my, g.died_on, g.section, g.plot
         FROM graves g
         WHERE g.name_en LIKE :q1 OR g.name_my LIKE :q2 OR g.name_ar LIKE :q4 OR g.name_community_my LIKE :q5 OR g.grave_no = :q3
         ORDER BY g.name_en LIMIT 50'
    );
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $q, ':q4' => $like, ':q5' => $like]);
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
$qs = $q === '' ? '' : '?q=' . urlencode($q);
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('results') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/en/search<?= htmlspecialchars($qs) ?>"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/my/search<?= htmlspecialchars($qs) ?>"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/<?= $lang ?>/">&larr; <?= t('home') ?></a>
    <h1 class="ti"><?= t('results') ?></h1>
    <p class="su"><?= tf('results_found', count($results)) ?></p>
    <form action="/<?= $lang ?>/search" method="get">
      <input class="in" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= t('search_placeholder') ?>" aria-label="<?= t('search_placeholder') ?>">
    </form>
  </div>
</header>
<main class="container">
<?php if ($q === ''): ?>
  <p class="empty"><?= t('type_name') ?></p>
<?php elseif (!$results): ?>
  <p class="empty"><?= tf('no_results', $q) ?></p>
<?php else: ?>
  <div class="list">
  <?php foreach ($results as $r): ?>
    <a class="card res" href="/<?= $lang ?>/grave/<?= (int)$r['id'] ?>">
      <span class="av"><?= htmlspecialchars(initials($r['name_en'])) ?></span>
      <span class="grow">
        <b><?= htmlspecialchars($r['name_en']) ?></b><br>
        <span class="sm"><?= htmlspecialchars($r['name_my'] ?? '') ?> � <?= t('section') ?> <?= htmlspecialchars($r['section']) ?>, <?= t('plot') ?> <?= htmlspecialchars($r['plot']) ?></span>
      </span>
      <?php if ($r['died_on']): ?><span class="yr"><?= date('Y', strtotime($r['died_on'])) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>
