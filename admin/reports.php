<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();

function rep(PDO $pdo, string $sql, array $extra = []): array {
    $p = $extra;
    $sc = cemetery_scope_sql('g.cemetery_id', $p);
    $st = $pdo->prepare(str_replace('{SCOPE}', $sc, $sql));
    $st->execute($p);
    return $st->fetchAll();
}

$tot = rep($pdo, "SELECT COUNT(*) total, SUM(g.gender='male') male, SUM(g.gender='female') female,
    SUM(g.gender IS NULL OR g.gender='') unk, AVG(g.age) avg_age, SUM(g.section='TBC') tbc,
    SUM(g.died_on IS NULL) nodate, SUM(g.age IS NULL) noage,
    SUM(g.name_my IS NOT NULL AND g.name_en = g.name_my) noen,
    SUM(g.created_at >= (NOW() - INTERVAL 30 DAY)) added30
    FROM graves g WHERE {SCOPE}")[0];
$T = fn($k) => (int)($tot[$k] ?? 0);

$byYear = rep($pdo, "SELECT YEAR(g.died_on) y, SUM(g.gender='male') m, SUM(g.gender='female') f,
    SUM(g.gender IS NULL OR g.gender='') u, COUNT(*) t
    FROM graves g WHERE {SCOPE} GROUP BY y ORDER BY (y IS NULL), y DESC");
$years = array_values(array_filter(array_map(fn($r) => $r['y'] === null ? null : (int)$r['y'], $byYear)));
$yr = (int)($_GET['year'] ?? 0);
if (!in_array($yr, $years, true)) { $yr = $years[0] ?? 0; }
$monthly = $yr ? rep($pdo, "SELECT MONTH(g.died_on) mo, COUNT(*) n FROM graves g
    WHERE {SCOPE} AND YEAR(g.died_on) = :yr GROUP BY mo", [':yr' => $yr]) : [];
$mon = array_fill(1, 12, 0);
foreach ($monthly as $r) { $mon[(int)$r['mo']] = (int)$r['n']; }
$monthNames = $lang === 'my'
    ? ['', 'ဇန်နဝါရီ', 'ဖေဖော်ဝါရီ', 'မတ်', 'ဧပြီ', 'မေ', 'ဇွန်', 'ဇူလိုင်', 'ဩဂုတ်', 'စက်တင်ဘာ', 'အောက်တိုဘာ', 'နိုဝင်ဘာ', 'ဒီဇင်ဘာ']
    : ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

$ages = rep($pdo, "SELECT CASE WHEN g.age IS NULL THEN 'u' WHEN g.age < 13 THEN 'a' WHEN g.age < 40 THEN 'b'
    WHEN g.age < 60 THEN 'c' WHEN g.age < 80 THEN 'd' ELSE 'e' END grp,
    SUM(g.gender='male') m, SUM(g.gender='female') f, COUNT(*) t
    FROM graves g WHERE {SCOPE} GROUP BY grp");
$ageBy = [];
foreach ($ages as $r) { $ageBy[$r['grp']] = $r; }

$towns = rep($pdo, "SELECT COALESCE(NULLIF(g.home_city_my,''), g.home_city_en) k, MAX(NULLIF(g.home_city_en,'')) en, COUNT(*) n
    FROM graves g WHERE {SCOPE} AND (COALESCE(g.home_city_my,'') <> '' OR COALESCE(g.home_city_en,'') <> '')
    GROUP BY k ORDER BY n DESC LIMIT 10");

$blocks = rep($pdo, "SELECT m.name_en cem_en, m.name_my cem_my, g.section, COUNT(*) n
    FROM graves g JOIN cemeteries m ON m.id = g.cemetery_id
    WHERE {SCOPE} GROUP BY g.cemetery_id, g.section ORDER BY m.name_en, g.section");

$maxYear = max(array_merge([1], array_map(fn($r) => (int)$r['t'], $byYear)));
$maxMon = max(array_merge([1], array_values($mon)));
$maxTown = max(array_merge([1], array_map(fn($r) => (int)$r['n'], $towns)));
$maxBlock = max(array_merge([1], array_map(fn($r) => (int)$r['n'], $blocks)));
$pct = fn($n, $max) => $max > 0 ? round($n * 100 / $max) : 0;
$total = $T('total');
$confirmed = $total - $T('tbc');
$pageTitle = t('reports');
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
  <div class="container wide">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/reports.php?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/reports.php?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/">&larr; <?= t('admin') ?></a>
    <h1 class="ti"><?= t('reports') ?></h1>
  </div>
</header>
<main class="container wide">
<?php if ($total === 0): ?>
  <p class="empty"><?= t('no_burials') ?></p>
<?php else: ?>
  <div class="kpis">
    <div class="kpi"><b><?= $total ?></b><span><?= t('rep_total') ?></span></div>
    <div class="kpi"><b><?= $T('male') ?></b><span><?= t('male') ?></span></div>
    <div class="kpi"><b><?= $T('female') ?></b><span><?= t('female') ?></span></div>
    <div class="kpi"><b><?= $T('unk') ?></b><span><?= t('not_recorded') ?></span></div>
    <div class="kpi"><b><?= $tot['avg_age'] !== null ? round((float)$tot['avg_age']) : '-' ?></b><span><?= t('rep_avg_age') ?></span></div>
    <div class="kpi"><b><?= $T('added30') ?></b><span><?= t('rep_added30') ?></span></div>
  </div>

  <div class="card rep">
    <h2><?= t('rep_to_check') ?></h2>
    <p class="sm" style="margin:0 0 6px"><?= tf('rep_progress', $confirmed, $total) ?></p>
    <div class="sbar" style="margin-bottom:12px"><i style="width:<?= $pct($confirmed, $total) ?>%;background:var(--green)"></i></div>
    <div class="hb"><span class="l" style="width:auto;flex:1"><?= t('rep_tbc') ?></span><span class="n"><?= $T('tbc') ?></span><a href="/admin/graves.php?tbc=1"><?= t('rep_open') ?></a></div>
    <div class="hb"><span class="l" style="width:auto;flex:1"><?= t('rep_noen') ?></span><span class="n"><?= $T('noen') ?></span></div>
    <div class="hb"><span class="l" style="width:auto;flex:1"><?= t('rep_nodate') ?></span><span class="n"><?= $T('nodate') ?></span></div>
    <div class="hb"><span class="l" style="width:auto;flex:1"><?= t('rep_noage') ?></span><span class="n"><?= $T('noage') ?></span></div>
    <div class="hb"><span class="l" style="width:auto;flex:1"><?= t('rep_nogender') ?></span><span class="n"><?= $T('unk') ?></span></div>
  </div>

  <div class="card rep">
    <h2><?= t('rep_by_year') ?></h2>
    <div class="legend"><span><i style="background:#0F4C3A"></i><?= t('male') ?></span><span><i style="background:#D9B866"></i><?= t('female') ?></span><span><i style="background:#B9C4BE"></i><?= t('not_recorded') ?></span></div>
    <div style="overflow-x:auto">
    <table class="rt">
      <tr><th><?= t('rep_year') ?></th><th><?= t('male') ?></th><th><?= t('female') ?></th><th><?= t('not_recorded') ?></th><th><?= t('rep_total_col') ?></th><th></th></tr>
      <?php foreach ($byYear as $r): $m = (int)$r['m']; $f = (int)$r['f']; $u = (int)$r['u']; $tt = (int)$r['t']; ?>
      <tr>
        <td><?= $r['y'] === null ? t('rep_no_date') : (int)$r['y'] ?></td>
        <td><?= $m ?></td><td><?= $f ?></td><td><?= $u ?></td><td><b><?= $tt ?></b></td>
        <td style="width:34%"><div class="sbar" style="width:<?= $pct($tt, $maxYear) ?>%"><i style="width:<?= $pct($m, $tt) ?>%;background:#0F4C3A"></i><i style="width:<?= $pct($f, $tt) ?>%;background:#D9B866"></i><i style="width:<?= $pct($u, $tt) ?>%;background:#B9C4BE"></i></div></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  </div>

  <?php if ($years): ?>
  <div class="card rep">
    <h2><?= t('rep_monthly') ?></h2>
    <form method="get" action="/admin/reports.php" style="margin:0 0 10px">
      <select class="in" name="year" onchange="this.form.submit()" aria-label="<?= t('rep_year') ?>" style="height:44px">
        <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= $y === $yr ? ' selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php for ($i = 1; $i <= 12; $i++): ?>
    <div class="hb"><span class="l"><?= htmlspecialchars($monthNames[$i]) ?></span><span class="b"><i style="width:<?= $pct($mon[$i], $maxMon) ?>%"></i></span><span class="n"><?= $mon[$i] ?></span></div>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <div class="card rep">
    <h2><?= t('rep_age_groups') ?></h2>
    <div class="legend"><span><i style="background:#0F4C3A"></i><?= t('male') ?></span><span><i style="background:#D9B866"></i><?= t('female') ?></span><span><i style="background:#B9C4BE"></i><?= t('not_recorded') ?></span></div>
    <?php $maxAge = max(array_merge([1], array_map(fn($r) => (int)$r['t'], $ages))); ?>
    <?php foreach (['a', 'b', 'c', 'd', 'e', 'u'] as $g):
        $r = $ageBy[$g] ?? ['m' => 0, 'f' => 0, 't' => 0]; $tt = (int)$r['t']; $m = (int)$r['m']; $f = (int)$r['f']; $u = $tt - $m - $f; ?>
    <div class="hb"><span class="l"><?= t('age_' . $g) ?></span>
      <span class="b" style="background:none"><span class="sbar" style="width:<?= $pct($tt, $maxAge) ?>%"><i style="width:<?= $pct($m, $tt) ?>%;background:#0F4C3A"></i><i style="width:<?= $pct($f, $tt) ?>%;background:#D9B866"></i><i style="width:<?= $pct($u, $tt) ?>%;background:#B9C4BE"></i></span></span>
      <span class="n"><?= $tt ?></span></div>
    <?php endforeach; ?>
  </div>

  <?php if ($towns): ?>
  <div class="card rep">
    <h2><?= t('rep_hometowns') ?></h2>
    <?php foreach ($towns as $r): $n = (int)$r['n']; $label = $lang === 'my' ? $r['k'] : ($r['en'] ?: $r['k']); ?>
    <div class="hb"><span class="l"><?= htmlspecialchars((string)$label) ?></span><span class="b"><i style="width:<?= $pct($n, $maxTown) ?>%"></i></span><span class="n"><?= $n ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card rep" style="margin-bottom:24px">
    <h2><?= t('rep_by_block') ?></h2>
    <?php foreach ($blocks as $r): $n = (int)$r['n']; ?>
    <div class="hb"><span class="l" style="width:150px"><?= htmlspecialchars(($lang === 'my' && $r['cem_my']) ? $r['cem_my'] : $r['cem_en']) ?> &middot; <?= htmlspecialchars($r['section']) ?></span><span class="b"><i style="width:<?= $pct($n, $maxBlock) ?>%;<?= $r['section'] === 'TBC' ? 'background:#D9B866' : '' ?>"></i></span><span class="n"><?= $n ?></span></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>