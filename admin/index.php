<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();

$cemName = '';
if ($admin['cemetery_id'] !== null) {
    $s = $pdo->prepare('SELECT name_en, name_my FROM cemeteries WHERE id = :id');
    $s->execute([':id' => $admin['cemetery_id']]);
    $c = $s->fetch();
    if ($c) { $cemName = ($lang === 'my' && $c['name_my']) ? $c['name_my'] : $c['name_en']; }
}
$scopeHtml = $cemName !== '' ? htmlspecialchars($cemName) : t('all_cemeteries');
if ($admin['cemetery_id'] !== null) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM graves WHERE cemetery_id = :c');
    $q->execute([':c' => $admin['cemetery_id']]);
    $count = (int)$q->fetchColumn();
} else {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM graves')->fetchColumn();
}

$groups = [
    ['menu_burials', [
        [can('burial.create'), '/admin/grave-add.php', 'add_burial'],
        [true, '/admin/graves.php', 'burial_list'],
    ]],
    ['menu_setup', [
        [can('block.edit'), '/admin/sections.php', 'blocks'],
        [can('admin.manage'), '/admin/cemeteries.php', 'cemeteries'],
        [can('admin.manage'), '/admin/cities.php', 'cities'],
    ]],
    ['menu_people', [
        [can('admin.manage'), '/admin/admins.php', 'admins'],
        [can('admin.manage'), '/admin/activity.php', 'activity'],
    ]],
    ['menu_account', [
        [true, '/admin/password.php', 'pw_change'],
        [true, '/logout.php', 'logout'],
    ]],
];
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= t('admin') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero hero-sm">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/?lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/?lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <h1 class="ti"><?= t('admin') ?></h1>
    <p class="su"><?= tf('logged_in_as', $admin['username']) ?></p>
    <p class="su" style="margin-top:2px"><?= t('role_' . $admin['role']) ?> &middot; <?= $scopeHtml ?> &middot; <?= tf('burial_n', $count) ?></p>
  </div>
</header>
<main class="container">
<?php foreach ($groups as [$heading, $items]): ?>
  <?php $shown = array_filter($items, fn($i) => $i[0]); ?>
  <?php if (!$shown) { continue; } ?>
  <h2 class="sec"><?= t($heading) ?></h2>
  <div class="card list-card">
  <?php foreach ($shown as [, $href, $label]): ?>
    <a class="irow" href="<?= $href ?>"><?= t($label) ?><span>&rsaquo;</span></a>
  <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</main>
</body>
</html>