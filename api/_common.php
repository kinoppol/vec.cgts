<?php
// ใช้ร่วมกันใน API ทุกไฟล์
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('vec_cgts_sess');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ป้องกัน PHP error/warning หลุดออกมาปนกับ JSON response (ทำให้ฝั่ง client parse JSON พังทั้งหน้า)
ini_set('display_errors', '0');
ob_start();

function json_out(mixed $data, int $code = 200): never {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    json_out(['error' => 'เกิดข้อผิดพลาดที่ระบบ — กรุณาติดต่อผู้ดูแลระบบ'], 500);
});

function err(string $msg, int $code = 400): never {
    json_out(['error' => $msg], $code);
}

function require_auth(): array {
    if (empty($_SESSION['user_id'])) {
        err('Unauthorized', 401);
    }
    return ['id' => $_SESSION['user_id'], 'role' => $_SESSION['role']];
}

function require_user_manager(): array {
    $u = require_auth();
    if ($u['role'] !== 'admin' && empty($_SESSION['can_manage_users'])) {
        err('Forbidden', 403);
    }
    return $u;
}

function audit(string $action, ?string $target = null, ?string $detail = null): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO audit_log (user_id, action, target_id, detail, ip) VALUES (?,?,?,?,?)')
           ->execute([$_SESSION['user_id'] ?? null, $action, $target, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable) {}
}
