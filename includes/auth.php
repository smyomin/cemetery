<?php
require_once __DIR__ . '/config.php';

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
    if (empty($_SESSION['admin'])) {
        header('Location: /login.php');
        exit;
    }
    return $_SESSION['admin'];
}

function grave_no_taken(PDO $pdo, int $cem, string $no, int $exceptId): bool {
    $s = $pdo->prepare('SELECT COUNT(*) FROM graves WHERE cemetery_id = :c AND grave_no = :n AND id <> :i');
    $s->execute([':c' => $cem, ':n' => $no, ':i' => $exceptId]);
    return (int)$s->fetchColumn() > 0;
}