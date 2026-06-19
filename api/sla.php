<?php
require_once __DIR__ . '/_common.php';

$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

/* GET — เปิดให้ทุก role ที่ล็อกอินแล้ว (ใช้แสดงข้อมูล SLA ในระบบ) */
if ($method === 'GET') {
    $rows = $db->query("
        SELECT s.*, u.display_name AS updated_by_name
        FROM sla_settings s
        LEFT JOIN users u ON u.id = s.updated_by
        ORDER BY FIELD(s.track,'discipline','legal'), s.cat
    ")->fetchAll();
    json_out($rows);
}

/* POST/PATCH — เฉพาะ admin และ dir_legal */
if (!in_array($actor['role'], ['admin', 'dir_legal'])) err('ไม่มีสิทธิ์แก้ไขการตั้งค่า SLA', 403);

if ($method === 'POST' || $method === 'PATCH') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $track = $body['track'] ?? '';
    $cat   = trim($body['cat'] ?? '');
    $days  = (int)($body['days'] ?? 0);
    $note  = trim($body['note'] ?? '') ?: null;

    if (!in_array($track, ['discipline', 'legal'])) err('track ไม่ถูกต้อง');
    if ($cat === '') err('กรุณาระบุหมวดงาน');
    if ($days < 1 || $days > 3650) err('จำนวนวันต้องอยู่ระหว่าง 1 ถึง 3650');

    $uid = (int)$actor['id'];
    $db->prepare("
        INSERT INTO sla_settings (track, cat, days, note, updated_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          days       = VALUES(days),
          note       = VALUES(note),
          updated_by = VALUES(updated_by),
          updated_at = NOW()
    ")->execute([$track, $cat, $days, $note, $uid]);

    audit('sla_update', "{$track}/{$cat}", "days={$days}");

    $row = $db->prepare("
        SELECT s.*, u.display_name AS updated_by_name
        FROM sla_settings s
        LEFT JOIN users u ON u.id = s.updated_by
        WHERE s.track = ? AND s.cat = ?
    ");
    $row->execute([$track, $cat]);
    json_out($row->fetch());
}

err('Method not allowed', 405);
