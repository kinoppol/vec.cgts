<?php
require_once __DIR__ . '/_common.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET /api/auth.php — ดึงข้อมูลผู้ใช้ปัจจุบัน
if ($method === 'GET') {
    if (empty($_SESSION['user_id'])) {
        json_out(null);
    }
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, display_name, role, init FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    json_out($user ?: null);
}

// POST /api/auth.php — เข้าสู่ระบบ
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        err('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, display_name, role, init FROM users WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        err('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];

    audit('login', null, 'เข้าสู่ระบบสำเร็จ');

    json_out([
        'id'           => $user['id'],
        'username'     => $user['username'],
        'display_name' => $user['display_name'],
        'role'         => $user['role'],
        'init'         => $user['init'],
    ]);
}

// DELETE /api/auth.php — ออกจากระบบ
if ($method === 'DELETE') {
    audit('logout');
    session_destroy();
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
