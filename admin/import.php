<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('burial.create');

$params = [];
$scope = cemetery_scope_sql('CAST(f.cemetery_id AS UNSIGNED)', $params);
$q = $pdo->prepare("SELECT f.fb_post_id, f.name_my, f.name_community_my, f.died_on, f.age, f.review_flags
    FROM fb_import f
    WHERE f.import_status = 'new' AND f.cemetery_id <> '' AND $scope
    ORDER BY f.died_on DESC LIMIT 300");
$q->execute($params);
$rows = $q->fetchAll();
$cnt = $pdo->query('SELECT import_status, COUNT(*) FROM fb_import GROUP BY import_status')->fetchAll(PDO::FETCH_KEY_PAIR);
$done = $_GET['done'] ?? '';
$pageTitle = t('import_title');
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
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/import.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/import.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('import_title') ?></h1>
    <p class="su"><?= t('import_intro') ?></p>
  </div>
</header>
<main class="container">
  <?php if ($done === 'saved'): ?><p class="ok" style="margin:16px 20px 0"><?= t('import_saved') ?></p><?php endif; ?>
  <?php if ($done === 'skipped'): ?><p class="ok" style="margin:16px 20px 0"><?= t('import_skipped') ?></p><?php endif; ?>
  <p class="sm" style="margin:16px 20px 0"><?= tf('import_counts', (int)($cnt['new'] ?? 0), (int)($cnt['imported'] ?? 0), (int)($cnt['skip'] ?? 0), (int)($cnt['hold'] ?? 0)) ?></p>
<?php if (!$rows): ?>
  <p class="empty"><?= t('import_none') ?></p>
<?php else: ?>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <?php $nf = $r['review_flags'] === '' || $r['review_flags'] === null ? 0 : substr_count($r['review_flags'], ' ; ') + 1; ?>
    <a class="card res" href="/admin/import-review.php?id=<?= urlencode($r['fb_post_id']) ?>">
      <span class="grow">
        <b><?= htmlspecialchars($r['name_my'] ?: $r['name_community_my']) ?></b><br>
        <span class="sm"><?= htmlspecialchars((string)$r['name_community_my']) ?> &middot; <?= htmlspecialchars((string)$r['died_on']) ?><?= $r['age'] !== '' && $r['age'] !== null ? ' &middot; ' . (int)$r['age'] : '' ?></span>
      </span>
      <?php if ($nf): ?><span class="yr"><?= tf('import_flag_n', $nf) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>