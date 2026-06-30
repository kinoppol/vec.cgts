<?php
/* ============================================================
   api/case_events.php — สร้างและอัปเดต event ต่อขั้นตอน
   POST          — สร้าง event ใหม่สำหรับ step (officer ขึ้นไป)
   PATCH ?id=    — อัปเดต event ที่มีอยู่แล้ว
   ============================================================ */
require_once __DIR__ . '/_common.php';

$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$id     = (int)($_GET['id'] ?? 0);

/* ── POST: สร้าง event ใหม่ ──────────────────────────────── */
if ($method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $case_id  = trim($body['case_id']  ?? '');
    $step_key = trim($body['step_key'] ?? '');
    $ev_status= $body['ev_status'] ?? 'active';

    if (!$case_id)  err('ต้องระบุ case_id');
    if (!$step_key) err('ต้องระบุ step_key');
    if (!in_array($ev_status, ['done','active','pending'])) err('ev_status ไม่ถูกต้อง');

    // ตรวจว่า case มีอยู่
    $cs = $db->prepare("SELECT id FROM cases WHERE id = ?");
    $cs->execute([$case_id]);
    if (!$cs->fetch()) err('ไม่พบสำนวน', 404);

    // ดึง step info
    $sp = $db->prepare("SELECT * FROM sla_steps WHERE step_key = ?");
    $sp->execute([$step_key]);
    $step = $sp->fetch();
    if (!$step) err('ไม่พบ step_key', 404);

    // ตรวจว่ามี event สำหรับ step นี้อยู่แล้วหรือไม่
    $ex = $db->prepare("SELECT id FROM case_events WHERE case_id = ? AND step_key = ?");
    $ex->execute([$case_id, $step_key]);
    if ($ex->fetch()) err('มี event สำหรับขั้นตอนนี้อยู่แล้ว', 409);

    $now = date('Y-m-d H:i:s');
    $started_at   = ($ev_status === 'active' || $ev_status === 'done') ? $now : null;
    $completed_at = ($ev_status === 'done') ? $now : null;

    $db->prepare("
        INSERT INTO case_events
          (case_id, title, ev_status, icon, sort_order, step_key, started_at, completed_at)
        VALUES (?, ?, ?, 'dot', ?, ?, ?, ?)
    ")->execute([
        $case_id, $step['label'], $ev_status,
        (int)$step['sort_order'], $step_key,
        $started_at, $completed_at,
    ]);
    $new_id = (int)$db->lastInsertId();

    audit('event_create', $case_id, "step={$step_key}");

    $row = $db->prepare("SELECT * FROM case_events WHERE id = ?");
    $row->execute([$new_id]);
    json_out($row->fetch());
}

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

    // ยืนยันว่ากด "เสร็จแล้ว" ต้องมี detail หรือ attachment
    if ($st === 'done') {
        $newDetail     = trim($body['detail'] ?? $event['detail'] ?? '');
        $hasAttachment = !empty($event['attachment_path']) || !empty($body['_has_file']);
        if ($newDetail === '' && !$hasAttachment) {
            err('กรุณาพิมพ์บันทึกการดำเนินการ หรือแนบไฟล์ PDF อย่างน้อยหนึ่งอย่าง');
        }
    }

    $sets[] = 'ev_status = ?'; $vals[] = $st;
    // auto-fill dates
    if ($st === 'active' && !$event['started_at']) {
        $sets[] = 'started_at = NOW()';
    }
    if ($st === 'done' && !$event['completed_at']) {
        $sets[] = 'completed_at = NOW()';
        if (!$event['started_at']) { $sets[] = 'started_at = NOW()'; }
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
if (array_key_exists('attachment_name', $body)) {
    $sets[] = 'attachment_name = ?'; $vals[] = trim($body['attachment_name']) ?: null;
}
if (array_key_exists('attachment_path', $body)) {
    $sets[] = 'attachment_path = ?'; $vals[] = trim($body['attachment_path']) ?: null;
}
if (array_key_exists('attachment_size', $body)) {
    $sets[] = 'attachment_size = ?'; $vals[] = trim($body['attachment_size']) ?: null;
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
