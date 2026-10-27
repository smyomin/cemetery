<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('admin.manage');

$id = (int)($_GET['id'] ?? 0);
$row = null;
if ($id > 0) {
    $s = $pdo->prepare('SELECT id, username, role, cemetery_id, active FROM admins WHERE id = :id');
    $s->execute([':id' => $id]);
    $row = $s->fetch();
    if (!$row) {
        http_response_code(404);
        exit(t('not_found'));
    }
}
$isSelf = $row && (int)$row['id'] === $admin['id'];
$cems = $pdo->query('SELECT id, name_en, name_my FROM cemeteries ORDER BY name_en')->fetchAll();
$validCems = array_map(fn($c) => (int)$c['id'], $cems);
$roles = ['super_admin', 'manager', 'editor'];

$err = '';
$v = [
    'username' => $row['username'] ?? '',
    'role' => $row['role'] ?? 'editor',
    'cemetery_id' => (int)($row['cemetery_id'] ?? 0),
    'active' => $row ? (int)$row['active'] : 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    $v['username'] = $row ? $row['username'] : trim($_POST['username'] ?? '');
    $v['role'] = $isSelf ? 'super_admin' : ($_POST['role'] ?? '');
    $v['cemetery_id'] = (int)($_POST['cemetery_id'] ?? 0);
    $v['active'] = $isSelf ? 1 : (isset($_POST['active']) ? 1 : 0);
    $taken = false;
    if (!$row) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = :u');
        $c->execute([':u' => $v['username']]);
        $taken = (int)$c->fetchColumn() > 0;
    }
    if (!csrf_check()) {
        $err = 'err_session';
    } elseif (!$row && !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $v['username'])) {
        $err = 'err_username';
    } elseif ($taken) {
        $err = 'err_username_taken';
    } elseif (!in_array($v['role'], $roles, true)) {
        $err = 'err_role';
    } elseif ($v['role'] !== 'super_admin' && !in_array($v['cemetery_id'], $validCems, true)) {
        $err = 'err_assign';
    } elseif ((!$row || $pw !== '') && mb_strlen($pw) < 10) {
        $err = 'err_password';
    } else {
        $cem = $v['role'] === 'super_admin' ? null : $v['cemetery_id'];
        if (!$row) {
            $i = $pdo->prepare('INSERT INTO admins (cemetery_id, username, password_hash, role, active) VALUES (:c, :u, :h, :r, :a)');
            $i->execute([':c' => $cem, ':u' => $v['username'], ':h' => password_hash($pw, PASSWORD_DEFAULT), ':r' => $v['role'], ':a' => $v['active']]);
            $newAdmin = (int)$pdo->lastInsertId();
            log_activity($pdo, 'admin.create', 'admin', $newAdmin, $cem, $v['username'] . ' (' . $v['role'] . ')');
            header('Location: /admin/admin-edit.php?id=' . $newAdmin . '&saved=1');
            exit;
        }
        $sql = 'UPDATE admins SET role = :r, cemetery_id = :c, active = :a' . ($pw !== '' ? ', password_hash = :h' : '') . ' WHERE id = :id';
        $p = [':r' => $v['role'], ':c' => $cem, ':a' => $v['active'], ':id' => $id];
        if ($pw !== '') { $p[':h'] = password_hash($pw, PASSWORD_DEFAULT); }
        $pdo->prepare($sql)->execute($p);
        log_activity($pdo, 'admin.update', 'admin', $id, $cem, $v['username'] . ' (' . $v['role'] . ')' . ($pw !== '' ? ' + new password' : '') . ($v['active'] ? '' : ' [deactivated]'));
        header('Location: /admin/admin-edit.php?id=' . $id . '&saved=1');
        exit;
    }
}
$title = $row ? t('edit_admin') : t('add_admin');
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= $title ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;600;700&family=Noto+Sans+Myanmar:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="hero">
  <div class="container">
    <nav class="top tg" aria-label="Language">
      <a href="/admin/admin-edit.php?id=<?= $id ?>&amp;lang=en"<?= $lang === 'en' ? ' class="on"' : '' ?>>EN</a>
      <a href="/admin/admin-edit.php?id=<?= $id ?>&amp;lang=my"<?= $lang === 'my' ? ' class="on"' : '' ?>>&#x1019;&#x103C;&#x1014;&#x103A;&#x1019;&#x102C;</a>
    </nav>
    <a class="back" href="/admin/admins.php">&larr; <?= t('admins') ?></a>
    <h1 class="ti"><?= $title ?></h1>
  </div>
</header>
<main class="container">
  <form class="card search-card" method="post" action="/admin/admin-edit.php?id=<?= $id ?>" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?php if (isset($_GET['saved'])): ?><p class="ok"><?= t('changes_saved') ?></p><?php endif; ?>
    <?php if ($err): ?><p class="err"><?= t($err) ?></p><?php endif; ?>

    <label class="lbl" for="username"><?= t('username') ?></label>
    <input class="in" id="username" name="username" value="<?= htmlspecialchars($v['username']) ?>" maxlength="50"<?= $row ? ' readonly' : ' required' ?>>

    <label class="lbl" for="role"><?= t('role') ?></label>
    <select class="in" id="role" name="role"<?= $isSelf ? ' disabled' : '' ?>>
      <?php foreach ($roles as $r): ?><option value="<?= $r ?>"<?= $v['role'] === $r ? ' selected' : '' ?>><?= t('role_' . $r) ?></option><?php endforeach; ?>
    </select>
    <p class="sm" style="margin:8px 0 0"><?= $isSelf ? t('self_note') : t('role_help') ?></p>

    <div id="cemwrap">
      <label class="lbl" for="cemetery_id"><?= t('assigned_cemetery') ?></label>
      <select class="in" id="cemetery_id" name="cemetery_id">
        <option value="0"><?= t('choose_cemetery') ?></option>
        <?php foreach ($cems as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $v['cemetery_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= htmlspecialchars(($lang === 'my' && $c['name_my']) ? $c['name_my'] : $c['name_en']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <label style="display:flex;gap:10px;align-items:center;margin-top:16px;font-size:15px"><input type="checkbox" name="active" value="1"<?= $v['active'] ? ' checked' : '' ?><?= $isSelf ? ' disabled' : '' ?> style="width:22px;height:22px"> <?= t('active_label') ?></label>

    <label class="lbl" for="password"><?= $row ? t('new_password_edit') : t('new_password') ?></label>
    <input class="in" id="password" name="password" type="password" maxlength="72" autocomplete="new-password"<?= $row ? '' : ' required' ?>>

    <button class="btn" type="submit"><?= t('save_changes') ?></button>
  </form>
</main>
<script>
(function () {
  var r = document.getElementById('role'), w = document.getElementById('cemwrap');
  function s() { w.style.display = r.value === 'super_admin' ? 'none' : 'block'; }
  r.addEventListener('change', s);
  s();
})();
</script>
</body>
</html>