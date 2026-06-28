<?php
/* ============================================================
   api/case_events.php — อัปเดตวันที่และสถานะของ event
   PATCH ?id= — officer ขึ้นไป
   ============================================================ */
require_once __DIR__ . '/_common.php';

$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);

if ($method !== 'PATCH' || $id <= 0) err('Method not allowed', 405);

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ดึง event + ตรวจสอบ
$ev = $db->prepare("SELECT * FROM case_events WHERE id = ?");
$ev->execute([$id]);
$event = $ev->fetch();
if (!$event) err('ไม่พบ event', 404);

$sets = []; $vals = [];

if (array_key_exists('started_at', $body)) {
    $d = $body['started_at'] ? trim($body['started_at']) : null;
    if ($d && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) err('รูปแบบ started_at ไม่ถูกต้อง');
    $sets[] = 'started_at = ?'; $vals[] = $d;
}

if (array_key_exists('completed_at', $body)) {
    $d = $body['completed_at'] ? trim($body['completed_at']) : null;
    if ($d && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) err('รูปแบบ completed_at ไม่ถูกต้อง');
    $sets[] = 'completed_at = ?'; $vals[] = $d;
}

if (array_key_exists('ev_status', $body)) {
    $st = $body['ev_status'];
    if (!in_array($st, ['done','active','pending'])) err('ev_status ไม่ถูกต้อง');
    $sets[] = 'ev_status = ?'; $vals[] = $st;
    // auto-fill dates
    if ($st === 'active' && !$event['started_at']) {
        $sets[] = 'started_at = ?'; $vals[] = date('Y-m-d');
    }
    if ($st === 'done' && !$event['completed_at']) {
        $sets[] = 'completed_at = ?'; $vals[] = date('Y-m-d');
        if (!$event['started_at']) { $sets[] = 'started_at = ?'; $vals[] = date('Y-m-d'); }
    }
}

if (array_key_exists('detail', $body)) {
    $sets[] = 'detail = ?'; $vals[] = trim($body['detail']) ?: null;
}
if (array_key_exists('actor', $body)) {
    $sets[] = 'actor = ?'; $vals[] = trim($body['actor']) ?: null;
}
if (array_key_exists('moment', $body)) {
    $sets[] = 'moment = ?'; $vals[] = trim($body['moment']) ?: null;
}

if (empty($sets)) err('ไม่มีข้อมูลที่ต้องการแก้ไข');

$vals[] = $id;
$db->prepare("UPDATE case_events SET " . implode(', ', $sets) . " WHERE id = ?")
   ->execute($vals);

audit('event_update', (string)$id, implode(',', array_map(fn($s)=>explode(' ',$s)[0], $sets)));

// คืน event ที่อัปเดตแล้ว พร้อม SLA info
$step = null;
if ($event['step_key']) {
    $s = $db->prepare("SELECT * FROM sla_steps WHERE step_key = ?");
    $s->execute([$event['step_key']]);
    $step = $s->fetch();
}

$updated = $db->prepare("SELECT * FROM case_events WHERE id = ?");
$updated->execute([$id]);
$row = $updated->fetch();
$row = array_merge($row, computeStepSla($row, $step));

json_out($row);

function computeStepSla(array $ev, ?array $step): array {
    if (!$step || !$ev['step_key']) return [];
    $allowed = (int)$step['days_allowed'];
    $start   = $ev['started_at']   ? new DateTime($ev['started_at'])   : null;
    $done    = $ev['completed_at'] ? new DateTime($ev['completed_at']) : null;
    $today   = new DateTime(date('Y-m-d'));

    $used      = $start ? (int)$start->diff($done ?? $today)->days : null;
    $remaining = $used !== null ? $allowed - $used : null;
    $sla       = null;
    if ($remaining !== null) {
        if ($remaining < 0)           $sla = 'r';
        elseif ($remaining <= max(1, (int)($allowed * 0.25))) $sla = 'a';
        else                          $sla = 'g';
    }
    return [
        'step_days_allowed' => $allowed,
        'step_days_used'    => $used,
        'step_days_remain'  => $remaining,
        'step_sla'          => $sla,
    ];
}
