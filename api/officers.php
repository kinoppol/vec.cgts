<?php
require_once __DIR__ . '/_common.php';
$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$id     = isset($_GET['id']) ? trim($_GET['id']) : '';

function needAdmin($actor) {
    if ($actor['role'] !== 'admin' && empty($actor['can_manage_users']))
        err('ไม่มีสิทธิ์จัดการข้อมูลนิติกร', 403);
}

/* ── GET ─────────────────────────────────────────────────── */
if ($method === 'GET') {

    /* ?all=1  → ข้อมูลเต็ม รวมไม่ active (สำหรับหน้าจัดการ) */
    if (!empty($_GET['all'])) {
        needAdmin($actor);
        $rows = $db->query(
            "SELECT o.id, o.name, o.job_title, o.duty, o.group_name, o.init, o.active,
                    COUNT(c.id) AS `load`
             FROM officers o
             LEFT JOIN cases c ON c.assignee_id = o.id AND c.status NOT IN ('closed','rejected')
             GROUP BY o.id
             ORDER BY o.active DESC, o.id"
        )->fetchAll();
        json_out($rows);
    }

    /* default → active เท่านั้น + alias role/group (backward compat) */
    $rows = $db->query(
        "SELECT o.id, o.name, o.job_title AS role, o.duty, o.group_name AS `group`, o.init,
                COUNT(c.id) AS `load`
         FROM officers o
         LEFT JOIN cases c ON c.assignee_id = o.id AND c.status NOT IN ('closed','rejected')
         WHERE o.active = 1
         GROUP BY o.id
         ORDER BY o.id"
    )->fetchAll();
    json_out($rows);
}

/* ── POST: สร้าง officer ────────────────────────────────── */
if ($method === 'POST') {
    needAdmin($actor);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $nid   = trim($body['id']        ?? '');
    $name  = trim($body['name']      ?? '');
    if (!$nid || !$name) err('id และ name จำเป็น', 400);

    $job   = trim($body['job_title'] ?? '');
    $duty  = trim($body['duty']      ?? '');
    $grp   = trim($body['group_name']?? '');
    $init  = trim($body['init']      ?? '');
    $act   = isset($body['active']) ? (int)$body['active'] : 1;

    /* ตรวจว่า id ซ้ำ */
    $chk = $db->prepare("SELECT id FROM officers WHERE id=?");
    $chk->execute([$nid]);
    if ($chk->fetch()) err("รหัส $nid มีอยู่แล้ว", 409);

    $db->prepare(
        "INSERT INTO officers (id, name, job_title, duty, group_name, init, active)
         VALUES (?,?,?,?,?,?,?)"
    )->execute([$nid, $name, $job ?: null, $duty ?: null, $grp ?: null, $init ?: null, $act]);

    audit('officer_create', $nid, $name);

    $row = $db->prepare("SELECT * FROM officers WHERE id=?")->execute([$nid]) ? null : null;
    $stmt = $db->prepare("SELECT * FROM officers WHERE id=?");
    $stmt->execute([$nid]);
    json_out($stmt->fetch());
}

/* ── PATCH ?id= : แก้ไข officer ─────────────────────────── */
if ($method === 'PATCH' && $id) {
    needAdmin($actor);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $allowed = ['name','job_title','duty','group_name','init','active'];
    $sets = []; $vals = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            $sets[] = "`$f`=?";
            $vals[] = $f === 'active' ? (int)$body[$f] : (trim($body[$f]) ?: null);
        }
    }
    if (!$sets) err('ไม่มีข้อมูลที่จะอัปเดต', 400);

    $vals[] = $id;
    $db->prepare("UPDATE officers SET " . implode(',', $sets) . " WHERE id=?")->execute($vals);
    audit('officer_update', $id, implode(', ', array_keys(array_intersect_key($body, array_flip($allowed)))));

    $stmt = $db->prepare("SELECT * FROM officers WHERE id=?");
    $stmt->execute([$id]);
    json_out($stmt->fetch());
}

/* ── DELETE ?id= : ปิดใช้งาน (soft delete) ─────────────── */
if ($method === 'DELETE' && $id) {
    needAdmin($actor);
    $db->prepare("UPDATE officers SET active=0 WHERE id=?")->execute([$id]);
    audit('officer_deactivate', $id);
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
