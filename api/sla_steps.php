<?php
/* ============================================================
   api/sla_steps.php — จัดการ SLA ต่อขั้นตอน
   GET  — ทุก role ที่ล็อกอินแล้ว
   PATCH ?id= — เฉพาะ admin / dir_legal
   ============================================================ */
require_once __DIR__ . '/_common.php';

$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    $rows = $db->query("
        SELECT s.*, u.display_name AS updated_by_name
        FROM sla_steps s
        LEFT JOIN users u ON u.id = s.updated_by
        ORDER BY s.sort_order
    ")->fetchAll();
    // cast types
    foreach ($rows as &$r) {
        $r['days_allowed'] = (int)$r['days_allowed'];
        $r['sort_order']   = (int)$r['sort_order'];
        $r['active']       = (bool)$r['active'];
    }
    json_out($rows);
}

if (!in_array($actor['role'], ['admin', 'dir_legal'])) err('ไม่มีสิทธิ์แก้ไขการตั้งค่า SLA', 403);

if ($method === 'PATCH' && $id > 0) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $days = isset($body['days_allowed']) ? (int)$body['days_allowed'] : null;
    $note = isset($body['note']) ? trim($body['note']) : null;

    if ($days !== null && ($days < 1 || $days > 3650)) err('จำนวนวันต้องอยู่ระหว่าง 1 ถึง 3650');

    $sets = [];
    $vals = [];
    if ($days !== null) { $sets[] = 'days_allowed = ?'; $vals[] = $days; }
    if ($note !== null) { $sets[] = 'note = ?';         $vals[] = $note ?: null; }
    if (empty($sets))   err('ไม่มีข้อมูลที่ต้องการแก้ไข');

    $sets[]  = 'updated_by = ?';
    $vals[]  = (int)$actor['id'];
    $vals[]  = $id;

    $db->prepare("UPDATE sla_steps SET " . implode(', ', $sets) . " WHERE id = ?")
       ->execute($vals);

    audit('sla_step_update', (string)$id, "days={$days}");

    $row = $db->prepare("
        SELECT s.*, u.display_name AS updated_by_name
        FROM sla_steps s LEFT JOIN users u ON u.id = s.updated_by
        WHERE s.id = ?
    ");
    $row->execute([$id]);
    $r = $row->fetch();
    $r['days_allowed'] = (int)$r['days_allowed'];
    $r['active']       = (bool)$r['active'];
    json_out($r);
}

err('Method not allowed', 405);
