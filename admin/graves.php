<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();

$q = trim($_GET['q'] ?? '');
$tbc = isset($_GET['tbc']) && $_GET['tbc'] === '1';
$params = [];
$where = [cemetery_scope_sql('g.cemetery_id', $params)];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(g.name_en LIKE :q1 OR g.name_my LIKE :q2 OR g.name_community_my LIKE :q3 OR g.name_ar LIKE :q4 OR g.grave_no = :q5)';
    $params[':q1'] = $like; $params[':q2'] = $like; $params[':q3'] = $like; $params[':q4'] = $like; $params[':q5'] = $q;
}
if ($tbc) { $where[] = "g.section = 'TBC'"; }
$w = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM graves g WHERE $w");
$c->execute($params);
$total = (int)$c->fetchColumn();
$per = 30;
$pages = max(1, (int)ceil($total / $per));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare("SELECT g.id, g.name_en, g.name_my, g.section, g.plot, g.died_on, m.name_en AS cemetery_en, m.name_my AS cemetery_my
    FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id
    WHERE $w ORDER BY g.id DESC LIMIT $per OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

function page_url(int $p, string $q, bool $tbc): string {
    $a = ['page' => $p];
    if ($q !== '') { $a['q'] = $q; }
    if ($tbc) { $a['tbc'] = '1'; }
    return '/admin/graves.php?' . http_build_query($a);
}
$pageTitle = t('burial_list');
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
</head>
<body>
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/graves.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/graves.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('burial_list') ?></h1>
    <p class="su"><?= tf('results_found', $total) ?></p>
  </div>
</header>
<main class="container">
  <?php if (isset($_GET['deleted'])): ?><p class="ok" style="margin:16px 20px 0"><?= t('deleted') ?></p><?php endif; ?>
  <form class="card search-card" method="get" action="/admin/graves.php">
    <input class="in" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= t('search_placeholder') ?>" aria-label="<?= t('search_placeholder') ?>">
    <label style="display:flex;gap:10px;align-items:center;margin-top:12px;font-size:14px"><input type="checkbox" name="tbc" value="1"<?= $tbc ? ' checked' : '' ?> style="width:22px;height:22px;flex:none"> <?= t('filter_tbc') ?></label>
    <button class="btn" type="submit"><?= t('search_button') ?></button>
  </form>
<?php if (!$rows): ?>
  <p class="empty"><?= t('no_burials') ?></p>
<?php else: ?>
  <div class="list">
  <?php foreach ($rows as $r): ?>
    <a class="card res" href="/admin/grave-edit.php?id=<?= (int)$r['id'] ?>">
      <span class="grow">
        <b><?= htmlspecialchars(($lang === 'my' && $r['name_my']) ? $r['name_my'] : $r['name_en']) ?></b><br>
        <span class="sm"><?= htmlspecialchars(($lang === 'my' && $r['cemetery_my']) ? $r['cemetery_my'] : $r['cemetery_en']) ?> &middot; <?= htmlspecialchars($r['section']) ?>/<?= htmlspecialchars($r['plot']) ?><?= $r['died_on'] ? ' &middot; ' . htmlspecialchars($r['died_on']) : '' ?></span>
      </span>
      <span class="yr"><?= t('edit') ?></span>
    </a>
  <?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0 20px 24px">
    <?php if ($page > 1): ?><a class="btn" style="width:auto;margin:0;padding:0 18px;text-decoration:none;display:flex;align-items:center" href="<?= htmlspecialchars(page_url($page - 1, $q, $tbc)) ?>">&larr; <?= t('prev') ?></a><?php else: ?><span></span><?php endif; ?>
    <span class="sm"><?= tf('page_of', $page, $pages) ?></span>
    <?php if ($page < $pages): ?><a class="btn" style="width:auto;margin:0;padding:0 18px;text-decoration:none;display:flex;align-items:center" href="<?= htmlspecialchars(page_url($page + 1, $q, $tbc)) ?>"><?= t('next') ?> &rarr;</a><?php else: ?><span></span><?php endif; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>