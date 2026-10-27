<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
$admin = require_admin();
require_can('block.edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    header('Location: /admin/sections.php');
    exit;
}
$id = (int)($_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT id, cemetery_id, code FROM sections WHERE id = :id');
$st->execute([':id' => $id]);
$s = $st->fetch();
if ($s && can_access_cemetery((int)$s['cemetery_id'])) {
    $nq = $pdo->prepare('SELECT COUNT(*) FROM graves WHERE cemetery_id = :c AND section = :k');
    $nq->execute([':c' => (int)$s['cemetery_id'], ':k' => $s['code']]);
    if ((int)$nq->fetchColumn() === 0) {
        $pdo->prepare('DELETE FROM sections WHERE id = :id')->execute([':id' => $id]);
        log_activity($pdo, 'block.delete', 'block', $id, (int)$s['cemetery_id'], $s['code']);
        header('Location: /admin/sections.php?deleted=1');
        exit;
    }
    header('Location: /admin/section-edit.php?id=' . $id);
    exit;
}
http_response_code(404);
exit(t('not_found'));