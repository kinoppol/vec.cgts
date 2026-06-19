<?php
require_once __DIR__ . '/_common.php';

$actor  = require_user_manager();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db     = getDB();

/* ----------------------------------------------------------------
   GET /api/users.php — รายการ users ทั้งหมด
---------------------------------------------------------------- */
if ($method === 'GET') {
    $rows = $db->query(
        "SELECT id, username, display_name, role, init, officer_id,
                active, can_manage_users, avatar_path, created_at
         FROM users ORDER BY role, display_name"
    )->fetchAll();
    json_out($rows);
}

/* ----------------------------------------------------------------
   POST /api/users.php — สร้าง user ใหม่
---------------------------------------------------------------- */
if ($method === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $username     = trim($b['username']     ?? '');
    $display_name = trim($b['display_name'] ?? '');
    $password     = $b['password']    ?? '';
    $role         = $b['role']        ?? 'officer';
    $init         = trim($b['init']   ?? '');
    $officer_id   = $b['officer_id']  ?: null;
    $can_mgr      = !empty($b['can_manage_users']) ? 1 : 0;

    if ($username === '')     err('กรุณาระบุชื่อผู้ใช้');
    if ($display_name === '') err('กรุณาระบุชื่อแสดง');
    if (strlen($password) < 6) err('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');

    $validRoles = ['officer','dir_legal','dir_admin','secretary','deputy_secretary','admin'];
    if (!in_array($role, $validRoles)) err('role ไม่ถูกต้อง');

    // ป้องกัน non-admin สร้าง admin
    if ($role === 'admin' && $actor['role'] !== 'admin') err('ไม่มีสิทธิ์สร้างบัญชี admin', 403);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $db->prepare(
            "INSERT INTO users (username, password_hash, display_name, role, init, officer_id, can_manage_users)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([$username, $hash, $display_name, $role, $init ?: null, $officer_id, $can_mgr]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) err("ชื่อผู้ใช้ '{$username}' มีอยู่แล้ว");
        throw $e;
    }

    $newId = (int)$db->lastInsertId();
    audit('user_create', (string)$newId, "สร้างบัญชี {$username}");
    $row = $db->prepare('SELECT id,username,display_name,role,init,officer_id,active,can_manage_users FROM users WHERE id=?');
    $row->execute([$newId]);
    json_out($row->fetch(), 201);
}

/* ----------------------------------------------------------------
   PATCH /api/users.php?id= — แก้ไข user
---------------------------------------------------------------- */
if ($method === 'PATCH' && $id) {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    // ดึงข้อมูลเดิม
    $cur = $db->prepare('SELECT * FROM users WHERE id=?');
    $cur->execute([$id]);
    $cur = $cur->fetch();
    if (!$cur) err('ไม่พบผู้ใช้', 404);

    // ป้องกันแก้ admin ถ้าไม่ใช่ admin
    if ($cur['role'] === 'admin' && $actor['role'] !== 'admin') err('ไม่มีสิทธิ์แก้ไขบัญชี admin', 403);

    $allowed = ['display_name','role','init','officer_id','active','can_manage_users'];
    $sets = []; $vals = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $b)) continue;
        if ($col === 'role') {
            if (!in_array($b[$col], ['officer','dir_legal','dir_admin','secretary','deputy_secretary','admin'])) err('role ไม่ถูกต้อง');
            if ($b[$col] === 'admin' && $actor['role'] !== 'admin') err('ไม่มีสิทธิ์ตั้ง role admin', 403);
        }
        $sets[] = "`{$col}` = ?";
        $vals[] = $b[$col] === '' ? null : $b[$col];
    }
    if (empty($sets)) err('ไม่มีข้อมูลที่จะแก้ไข');

    $vals[] = $id;
    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id=?')->execute($vals);
    audit('user_update', (string)$id, implode(', ', array_keys(array_intersect_key($b, array_flip($allowed)))));

    $row = $db->prepare('SELECT id,username,display_name,role,init,officer_id,active,can_manage_users FROM users WHERE id=?');
    $row->execute([$id]);
    json_out($row->fetch());
}

/* ----------------------------------------------------------------
   DELETE /api/users.php?id= — ปิดใช้งาน (soft delete)
---------------------------------------------------------------- */
if ($method === 'DELETE' && $id) {
    if ($id === (int)$actor['id']) err('ไม่สามารถลบบัญชีตัวเองได้');

    $cur = $db->prepare('SELECT role FROM users WHERE id=?');
    $cur->execute([$id]);
    $cur = $cur->fetch();
    if (!$cur) err('ไม่พบผู้ใช้', 404);
    if ($cur['role'] === 'admin' && $actor['role'] !== 'admin') err('ไม่มีสิทธิ์ลบบัญชี admin', 403);

    $db->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$id]);
    audit('user_deactivate', (string)$id);
    json_out(['ok' => true]);
}

/* ----------------------------------------------------------------
   POST /api/users.php?action=reset_pass&id= — รีเซ็ตรหัสผ่าน
---------------------------------------------------------------- */
if ($method === 'POST' && ($_GET['action'] ?? '') === 'reset_pass' && $id) {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $newPass = $b['password'] ?? '';
    if (strlen($newPass) < 6) err('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');

    $cur = $db->prepare('SELECT role FROM users WHERE id=?');
    $cur->execute([$id]);
    $cur = $cur->fetch();
    if (!$cur) err('ไม่พบผู้ใช้', 404);
    if ($cur['role'] === 'admin' && $actor['role'] !== 'admin') err('ไม่มีสิทธิ์รีเซ็ตรหัสผ่าน admin', 403);

    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $id]);
    audit('user_reset_pass', (string)$id);
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
