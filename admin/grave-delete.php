<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lang.php';
require __DIR__ . '/../includes/photo.php';
$admin = require_admin();
require_can('burial.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    header('Location: /admin/graves.php');
    exit;
}
$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT cemetery_id, person_photo, grave_photo FROM graves WHERE id = :id');
$stmt->execute([':id' => $id]);
$g = $stmt->fetch();
if ($g && ($admin['cemetery_id'] === null || (int)$g['cemetery_id'] === $admin['cemetery_id'])) {
    $pdo->prepare('DELETE FROM graves WHERE id = :id')->execute([':id' => $id]);
    delete_photo('people', $g['person_photo']);
    delete_photo('graves', $g['grave_photo']);
    header('Location: /admin/graves.php?deleted=1');
    exit;
}
http_response_code(404);
exit(t('not_found'));