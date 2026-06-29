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
        "SELECT u.id, u.username, u.display_name, u.email, u.role, u.init, u.job_title, u.group_name, u.officer_id,
                u.active, u.can_manage_users, u.avatar_path, u.created_at,
                (SELECT gr.role FROM group_roles gr
                 JOIN groups g ON g.id = gr.group_id
                 WHERE g.name = u.group_name
                 ORDER BY gr.role LIMIT 1) AS group_role
         FROM users u ORDER BY u.role, u.display_name"
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
    $officer_id   = $b['officer_id']  ?: null;
    $can_mgr      = !empty($b['can_manage_users']) ? 1 : 0;

    // ถ้าเชื่อมกับบุคลากร ดึง display_name/init/job_title/group_name จาก officers อัตโนมัติ
    $init = null; $job_title = null; $group_name = null;
    if ($officer_id) {
        $off = $db->prepare('SELECT name, init, job_title, group_name FROM officers WHERE id=?');
        $off->execute([$officer_id]);
        $off = $off->fetch();
        if ($off) {
            if ($display_name === '') $display_name = $off['name'];
            $init       = $off['init']       ?: null;
            $job_title  = $off['job_title']  ?: null;
            $group_name = $off['group_name'] ?: null;
        }
    }

    if ($username === '')     err('กรุณาระบุชื่อผู้ใช้');
    if ($display_name === '') err('กรุณาระบุชื่อแสดง (หรือเลือกบุคลากรที่เชื่อมโยง)');
    if (strlen($password) < 6) err('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');

    $validRoles = ['officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin'];
    $role = ($role === '' || $role === null) ? null : $role;
    if ($role !== null && !in_array($role, $validRoles)) err('role ไม่ถูกต้อง');

    // ป้องกัน non-admin สร้าง admin
    if ($role === 'admin' && $actor['role'] !== 'admin') err('ไม่มีสิทธิ์สร้างบัญชี admin', 403);
    // dir_legal สร้างได้เฉพาะ officer / head_secretary / null
    if ($actor['role'] === 'dir_legal' && !in_array($role, ['officer','head_secretary',null])) err('ไม่มีสิทธิ์กำหนด role นี้', 403);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $email_val = filter_var(trim($b['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
        $db->prepare(
            "INSERT INTO users (username, password_hash, display_name, email, role, init, job_title, group_name, officer_id, can_manage_users)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([$username, $hash, $display_name, $email_val, $role, $init ?: null, $job_title ?: null, $group_name ?: null, $officer_id, $can_mgr]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) err("ชื่อผู้ใช้ '{$username}' มีอยู่แล้ว");
        throw $e;
    }

    $newId = (int)$db->lastInsertId();
    audit('user_create', (string)$newId, "สร้างบัญชี {$username}");
    $row = $db->prepare('SELECT id,username,display_name,email,role,init,job_title,group_name,officer_id,active,can_manage_users FROM users WHERE id=?');
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

    // ถ้า officer_id เปลี่ยน ดึง display_name/init/job_title/group_name จาก officers อัตโนมัติ
    if (array_key_exists('officer_id', $b) && $b['officer_id'] && $b['officer_id'] != $cur['officer_id']) {
        $off = $db->prepare('SELECT name, init, job_title, group_name FROM officers WHERE id=?');
        $off->execute([$b['officer_id']]);
        $off = $off->fetch();
        if ($off) {
            if (empty($b['display_name'])) $b['display_name'] = $off['name'];
            $b['init']       = $off['init']       ?: null;
            $b['job_title']  = $off['job_title']  ?: null;
            $b['group_name'] = $off['group_name'] ?: null;
        }
    }

    $allowed = ['display_name','email','role','init','job_title','group_name','officer_id','active','can_manage_users'];
    $sets = []; $vals = [];

    // admin เปลี่ยน username ได้
    if ($actor['role'] === 'admin' && array_key_exists('username', $b)) {
        $newUsername = trim($b['username'] ?? '');
        if ($newUsername === '') err('ชื่อผู้ใช้ห้ามว่าง');
        if (!preg_match('/^[a-zA-Z0-9_@.]+$/', $newUsername)) err('ชื่อผู้ใช้ใช้ได้เฉพาะ a-z A-Z 0-9 _ @ .');
        // ตรวจ duplicate (ยกเว้นตัวเอง)
        $dup = $db->prepare('SELECT id FROM users WHERE username=? AND id!=?');
        $dup->execute([$newUsername, $id]);
        if ($dup->fetch()) err("ชื่อผู้ใช้ '{$newUsername}' มีอยู่แล้ว");
        $sets[] = '`username` = ?';
        $vals[] = $newUsername;
    }

    foreach ($allowed as $col) {
        if (!array_key_exists($col, $b)) continue;
        if ($col === 'role') {
            $validRoles = ['officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin'];
            if ($b[$col] !== null && $b[$col] !== '' && !in_array($b[$col], $validRoles)) err('role ไม่ถูกต้อง');
            if ($b[$col] === 'admin' && $actor['role'] !== 'admin') err('ไม่มีสิทธิ์ตั้ง role admin', 403);
            if ($actor['role'] === 'dir_legal' && !in_array($b[$col], ['officer','head_secretary',''])) err('ไม่มีสิทธิ์กำหนด role นี้', 403);
            $b[$col] = ($b[$col] === '' || $b[$col] === null) ? null : $b[$col];
            // ถ้า role=NULL แต่ column ยังไม่ nullable (migration [15] ยังไม่รัน) → skip
            if ($b[$col] === null) {
                $colInfo = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
                if ($colInfo && $colInfo['Null'] === 'NO') {
                    continue; // ไม่ update role ถ้า column ยังเป็น NOT NULL
                }
            }
        }
        $sets[] = "`{$col}` = ?";
        $vals[] = $b[$col] === '' ? null : $b[$col];
    }
    if (empty($sets)) err('ไม่มีข้อมูลที่จะแก้ไข');

    $vals[] = $id;
    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id=?')->execute($vals);
    audit('user_update', (string)$id, implode(', ', array_keys(array_intersect_key($b, array_flip($allowed)))));

    $row = $db->prepare('SELECT id,username,display_name,email,role,init,job_title,group_name,officer_id,active,can_manage_users,avatar_path FROM users WHERE id=?');
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
