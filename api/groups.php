<?php
require_once __DIR__ . '/_common.php';
$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

function needAdmin($actor) {
    if ($actor['role'] !== 'admin' && empty($actor['can_manage_users']))
        err('เฉพาะผู้ดูแลระบบเท่านั้น', 403);
}

function fetchGroupRoles($db, $groupId): array {
    $s = $db->prepare("SELECT role FROM group_roles WHERE group_id=? ORDER BY role");
    $s->execute([$groupId]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

/* ── GET ?id= — สมาชิก + บทบาทของกลุ่ม ───────────────────── */
if ($method === 'GET' && $id) {
    $stmt = $db->prepare(
        "SELECT g.id, g.name, g.leader_id, u.display_name AS leader_name, u.init AS leader_init
         FROM groups g LEFT JOIN users u ON u.id = g.leader_id WHERE g.id=?"
    );
    $stmt->execute([$id]);
    $group = $stmt->fetch();
    if (!$group) err('ไม่พบกลุ่ม', 404);

    $mstmt = $db->prepare(
        "SELECT id, username, display_name, role, init, group_name, job_title
         FROM users WHERE group_name = ? AND active = 1 ORDER BY display_name"
    );
    $mstmt->execute([$group['name']]);
    $group['members'] = $mstmt->fetchAll();
    $group['roles']   = fetchGroupRoles($db, $id);
    json_out($group);
}

/* ── GET — รายการกลุ่มทั้งหมด ──────────────────────────────── */
if ($method === 'GET') {
    $rows = $db->query(
        "SELECT g.id, g.name, g.leader_id,
                u.display_name AS leader_name, u.init AS leader_init,
                (SELECT COUNT(*) FROM users m WHERE m.group_name = g.name AND m.active = 1) AS member_count
         FROM groups g LEFT JOIN users u ON u.id = g.leader_id ORDER BY g.name"
    )->fetchAll();
    foreach ($rows as &$row) {
        $row['roles'] = fetchGroupRoles($db, (int)$row['id']);
    }
    json_out($rows);
}

/* ── POST ?action=add_role — เพิ่มบทบาทให้กลุ่ม ───────────── */
if ($method === 'POST' && $action === 'add_role' && $id) {
    needAdmin($actor);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $role = trim($body['role'] ?? '');
    if (!$role) err('role จำเป็น', 400);
    $db->prepare("INSERT IGNORE INTO group_roles (group_id, role) VALUES (?,?)")->execute([$id, $role]);
    audit('group_add_role', $id, $role);
    json_out(['ok' => true, 'roles' => fetchGroupRoles($db, $id)]);
}

/* ── DELETE ?action=remove_role — ถอดบทบาทออกจากกลุ่ม ─────── */
if ($method === 'DELETE' && $action === 'remove_role' && $id) {
    needAdmin($actor);
    $role = trim($_GET['role'] ?? '');
    if (!$role) err('role จำเป็น', 400);
    $db->prepare("DELETE FROM group_roles WHERE group_id=? AND role=?")->execute([$id, $role]);
    audit('group_remove_role', $id, $role);
    json_out(['ok' => true, 'roles' => fetchGroupRoles($db, $id)]);
}

/* ── POST ?action=add_member — เพิ่มสมาชิก (+ กำหนด role) ─── */
if ($method === 'POST' && $action === 'add_member' && $id) {
    needAdmin($actor);
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $userId = (int)($body['user_id'] ?? 0);
    $role   = trim($body['role'] ?? '');
    if (!$userId) err('user_id จำเป็น', 400);

    $grp = $db->prepare("SELECT name FROM groups WHERE id=?");
    $grp->execute([$id]);
    $grp = $grp->fetch();
    if (!$grp) err('ไม่พบกลุ่ม', 404);

    $update = "UPDATE users SET group_name=?";
    $params = [$grp['name']];
    if ($role) { $update .= ", role=?"; $params[] = $role; }
    $params[] = $userId;
    $db->prepare("$update WHERE id=?")->execute($params);

    audit('group_add_member', $id, "user_id=$userId" . ($role ? " role=$role" : ""));
    json_out(['ok' => true]);
}

/* ── DELETE ?action=remove_member — ลบสมาชิก ──────────────── */
if ($method === 'DELETE' && $action === 'remove_member' && $id) {
    needAdmin($actor);
    $userId = (int)($_GET['user_id'] ?? 0);
    if (!$userId) err('user_id จำเป็น', 400);

    $grp = $db->prepare("SELECT name, leader_id FROM groups WHERE id=?");
    $grp->execute([$id]);
    $grp = $grp->fetch();
    if (!$grp) err('ไม่พบกลุ่ม', 404);

    $db->prepare("UPDATE users SET group_name=NULL WHERE id=? AND group_name=?")->execute([$userId, $grp['name']]);
    if ((int)$grp['leader_id'] === $userId)
        $db->prepare("UPDATE groups SET leader_id=NULL WHERE id=?")->execute([$id]);

    audit('group_remove_member', $id, "user_id=$userId");
    json_out(['ok' => true]);
}

/* ── POST — สร้างกลุ่มใหม่ ──────────────────────────────────── */
if ($method === 'POST') {
    needAdmin($actor);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($body['name'] ?? '');
    if (!$name) err('name จำเป็น', 400);

    $dup = $db->prepare("SELECT id FROM groups WHERE name=?");
    $dup->execute([$name]);
    if ($dup->fetch()) err('ชื่อกลุ่มนี้มีอยู่แล้ว', 409);

    $db->prepare("INSERT INTO groups (name) VALUES (?)")->execute([$name]);
    $nid = (int)$db->lastInsertId();
    audit('group_create', $nid, $name);

    $stmt = $db->prepare(
        "SELECT g.id, g.name, g.leader_id, NULL AS leader_name, NULL AS leader_init, 0 AS member_count
         FROM groups g WHERE g.id=?"
    );
    $stmt->execute([$nid]);
    $row = $stmt->fetch();
    $row['roles'] = [];
    json_out($row, 201);
}

/* ── PATCH ?id= — แก้ไขชื่อ / เปลี่ยนหัวหน้า ──────────────── */
if ($method === 'PATCH' && $id) {
    needAdmin($actor);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $grp = $db->prepare("SELECT id, name, leader_id FROM groups WHERE id=?");
    $grp->execute([$id]);
    $grp = $grp->fetch();
    if (!$grp) err('ไม่พบกลุ่ม', 404);

    if (isset($body['name'])) {
        $newName = trim($body['name']);
        if (!$newName) err('name ห้ามว่าง', 400);
        $dup = $db->prepare("SELECT id FROM groups WHERE name=? AND id!=?");
        $dup->execute([$newName, $id]);
        if ($dup->fetch()) err('ชื่อกลุ่มนี้มีอยู่แล้ว', 409);
        $db->prepare("UPDATE users SET group_name=? WHERE group_name=?")->execute([$newName, $grp['name']]);
        $db->prepare("UPDATE groups SET name=? WHERE id=?")->execute([$newName, $id]);
        audit('group_rename', $id, "{$grp['name']} → $newName");
    }

    if (array_key_exists('leader_id', $body)) {
        $leaderId = $body['leader_id'] ? (int)$body['leader_id'] : null;
        $db->prepare("UPDATE groups SET leader_id=? WHERE id=?")->execute([$leaderId, $id]);
        audit('group_set_leader', $id, "leader_id=$leaderId");
    }

    $stmt = $db->prepare(
        "SELECT g.id, g.name, g.leader_id, u.display_name AS leader_name, u.init AS leader_init,
                (SELECT COUNT(*) FROM users m WHERE m.group_name = g.name AND m.active = 1) AS member_count
         FROM groups g LEFT JOIN users u ON u.id = g.leader_id WHERE g.id=?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $row['roles'] = fetchGroupRoles($db, $id);
    json_out($row);
}

/* ── DELETE ?id= — ลบกลุ่ม ─────────────────────────────────── */
if ($method === 'DELETE' && $id) {
    needAdmin($actor);
    $grp = $db->prepare("SELECT name FROM groups WHERE id=?");
    $grp->execute([$id]);
    $grp = $grp->fetch();
    if (!$grp) err('ไม่พบกลุ่ม', 404);

    $db->prepare("UPDATE users SET group_name=NULL WHERE group_name=?")->execute([$grp['name']]);
    $db->prepare("DELETE FROM groups WHERE id=?")->execute([$id]);
    audit('group_delete', $id, $grp['name']);
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
