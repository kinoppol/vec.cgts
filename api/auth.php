<?php
require_once __DIR__ . '/_common.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET /api/auth.php — ดึงข้อมูลผู้ใช้ปัจจุบัน
if ($method === 'GET') {
    if (empty($_SESSION['user_id'])) {
        json_out(null);
    }
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, display_name, role, group_name, init, can_manage_users, avatar_path FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) { json_out(null); }
    $user['role'] = resolveEffectiveRole($db, (int)$user['id'], $user['role'], $user['group_name']);
    $_SESSION['role'] = $user['role'];
    $user['can_manage_users']  = (bool)($user['can_manage_users'] ?? false);
    $user['is_impersonating']  = !empty($_SESSION['impersonator_id']);
    if ($user['is_impersonating']) {
        $user['impersonator_id']   = (int)$_SESSION['impersonator_id'];
        $user['impersonator_name'] = $_SESSION['impersonator_name'] ?? '';
    }
    json_out($user);
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
    $stmt = $db->prepare('SELECT id, username, password_hash, display_name, role, group_name, init, can_manage_users, avatar_path FROM users WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        err('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
    }

    $effectiveRole = resolveEffectiveRole($db, (int)$user['id'], $user['role'], $user['group_name']);

    session_regenerate_id(true);
    $_SESSION['user_id']          = $user['id'];
    $_SESSION['role']             = $effectiveRole;
    $_SESSION['can_manage_users'] = (bool)$user['can_manage_users'];

    audit('login', null, 'เข้าสู่ระบบสำเร็จ');

    json_out([
        'id'           => $user['id'],
        'username'     => $user['username'],
        'display_name' => $user['display_name'],
        'role'            => $effectiveRole,
        'can_manage_users'=> (bool)$user['can_manage_users'],
        'init'         => $user['init'],
        'avatar_path'  => $user['avatar_path'],
    ]);
}

// PATCH /api/auth.php — แก้ไขโปรไฟล์ของตัวเอง (ชื่อแสดง / ตัวย่อ / รหัสผ่าน)
if ($method === 'PATCH') {
    $actor = require_auth();
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    $db = getDB();
    $sets = []; $vals = [];

    if (array_key_exists('display_name', $b)) {
        $name = trim($b['display_name']);
        if ($name === '') err('กรุณาระบุชื่อแสดง');
        $sets[] = 'display_name = ?'; $vals[] = $name;
    }
    if (array_key_exists('init', $b)) {
        $sets[] = 'init = ?'; $vals[] = trim($b['init']) ?: null;
    }

    if (!empty($b['new_password'])) {
        $cur = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $cur->execute([$actor['id']]);
        $hash = $cur->fetchColumn();
        if (!$hash || !password_verify($b['current_password'] ?? '', $hash)) {
            err('รหัสผ่านปัจจุบันไม่ถูกต้อง');
        }
        if (strlen($b['new_password']) < 6) err('รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร');
        $sets[] = 'password_hash = ?';
        $vals[] = password_hash($b['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (empty($sets)) err('ไม่มีข้อมูลที่จะแก้ไข');

    $vals[] = $actor['id'];
    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    audit('profile_update', (string)$actor['id']);

    $row = $db->prepare('SELECT id, username, display_name, role, init, avatar_path FROM users WHERE id = ?');
    $row->execute([$actor['id']]);
    json_out($row->fetch());
}

// DELETE /api/auth.php — ออกจากระบบ
if ($method === 'DELETE') {
    audit('logout');
    session_destroy();
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
