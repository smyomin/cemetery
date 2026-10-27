<?php
require_once __DIR__ . '/config.php';

$CURRENT_ADMIN = null;

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function require_admin(): array {
    global $pdo, $CURRENT_ADMIN;
    $sid = (int)($_SESSION['admin']['id'] ?? 0);
    $row = false;
    if ($sid > 0) {
        $s = $pdo->prepare('SELECT id, username, role, cemetery_id, active FROM admins WHERE id = :id');
        $s->execute([':id' => $sid]);
        $row = $s->fetch();
    }
    $ok = $row
        && (int)$row['active'] === 1
        && in_array($row['role'], ['super_admin', 'manager', 'editor'], true)
        && ($row['role'] === 'super_admin' || $row['cemetery_id'] !== null);
    if (!$ok) {
        $_SESSION = [];
        header('Location: /login.php');
        exit;
    }
    $CURRENT_ADMIN = [
        'id' => (int)$row['id'],
        'username' => $row['username'],
        'role' => $row['role'],
        'cemetery_id' => $row['cemetery_id'] === null ? null : (int)$row['cemetery_id'],
    ];
    return $CURRENT_ADMIN;
}

function can(string $perm): bool {
    global $CURRENT_ADMIN;
    $role = $CURRENT_ADMIN['role'] ?? '';
    $map = [
        'burial.create' => ['super_admin', 'manager', 'editor'],
        'burial.edit'   => ['super_admin', 'manager', 'editor'],
        'burial.delete' => ['super_admin', 'manager'],
        'block.edit'    => ['super_admin', 'manager'],
        'admin.manage'  => ['super_admin'],
    ];
    return in_array($role, $map[$perm] ?? [], true);
}

function require_can(string $perm): void {
    if (!can($perm)) {
        http_response_code(403);
        exit('403 Forbidden');
    }
}

function grave_no_taken(PDO $pdo, int $cem, string $no, int $exceptId): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM graves WHERE cemetery_id = :c AND grave_no = :n AND id <> :i');
    $s->execute([':c' => $cem, ':n' => $no, ':i' => $exceptId]);
    return (int)$s->fetchColumn() > 0;
}
function log_activity(PDO $pdo, string $action, string $type, ?int $id, ?int $cem, string $details = '', ?array $who = null): void {
    global $CURRENT_ADMIN;
    $who = $who ?? $CURRENT_ADMIN;
    try {
        $pdo->prepare('INSERT INTO activity_log (created_at, admin_id, admin_username, action, target_type, target_id, cemetery_id, details) VALUES (:ts, :a, :u, :act, :t, :tid, :c, :d)')
            ->execute([':ts' => date('Y-m-d H:i:s'), ':a' => $who['id'] ?? null, ':u' => $who['username'] ?? '?', ':act' => $action, ':t' => $type, ':tid' => $id, ':c' => $cem, ':d' => mb_substr($details, 0, 255)]);
    } catch (Throwable $e) {
    }
}