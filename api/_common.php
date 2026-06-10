<?php
// ใช้ร่วมกันใน API ทุกไฟล์
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('vec_cgts_sess');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function json_out(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function err(string $msg, int $code = 400): never {
    json_out(['error' => $msg], $code);
}

function require_auth(): array {
    if (empty($_SESSION['user_id'])) {
        err('Unauthorized', 401);
    }
    return ['id' => $_SESSION['user_id'], 'role' => $_SESSION['role']];
}

function audit(string $action, ?string $target = null, ?string $detail = null): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO audit_log (user_id, action, target_id, detail, ip) VALUES (?,?,?,?,?)')
           ->execute([$_SESSION['user_id'] ?? null, $action, $target, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable) {}
}
