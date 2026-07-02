<?php
/* ============================================================
   api/case_report.php — การดำเนินการของนิติกรผู้ดำเนินการ + สายรายงาน
   POST { action, case_id, note?, file_count? }
     action = 'return'     → นิติกรเกษียนกลับให้ ผอ.กลุ่ม ทบทวน (ปลดล็อกการเปลี่ยนนิติกร)
     action = 'report'     → นิติกรรายงานผลการดำเนินการ (ข้อความ/แนบไฟล์)
     action = 'forward_up' → ผอ.กลุ่ม เกษียนรายงานผลถึง ผอ.สำนัก ผ่านธุรการ
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$db     = getDB();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed', 405);

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$caseId = trim($body['case_id'] ?? '');
if ($caseId === '') err('ต้องระบุ case_id');

$cs = $db->prepare("SELECT * FROM cases WHERE id = ?");
$cs->execute([$caseId]);
$case = $cs->fetch();
if (!$case) err('ไม่พบสำนวน', 404);

$caseCols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);

// ชื่อผู้กระทำ
$un = $db->prepare("SELECT display_name FROM users WHERE id = ?");
$un->execute([$auth['id']]);
$actorName = $un->fetchColumn() ?: 'ผู้ใช้';

$thMonth = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')];
$moment  = date('j') . ' ' . $thMonth . ' ' . (date('Y') + 543);

function addEvent(PDO $db, string $caseId, string $title, string $actor, string $moment, string $detail, string $icon): void {
    $maxOrd = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM case_events WHERE case_id = ?');
    $maxOrd->execute([$caseId]);
    $ord = $maxOrd->fetchColumn();
    $db->prepare("INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order, started_at)
                  VALUES (?,?,?,?,?, 'done', ?, ?, NOW())")
       ->execute([$caseId, $title, $actor, $moment, $detail, $icon, $ord]);
}

function notifyUser(PDO $db, $userId, string $caseId, string $type, string $title, string $body): void {
    if (!$userId) return;
    $db->prepare("INSERT INTO notifications (user_id, case_id, notif_type, title, body) VALUES (?,?,?,?,?)")
       ->execute([(int)$userId, $caseId, $type, $title, $body]);
}

// ผอ.กลุ่ม = หัวหน้ากลุ่ม (leader_id) ของกลุ่มที่ได้รับมอบหมาย
function groupLeaderUserId(PDO $db, ?string $groupName) {
    if (!$groupName) return null;
    try {
        $s = $db->prepare("SELECT leader_id FROM `groups` WHERE name = ? LIMIT 1");
        $s->execute([$groupName]);
        return $s->fetchColumn() ?: null;
    } catch (Throwable) { return null; }
}

$subjShort = mb_substr($case['subject'] ?? $caseId, 0, 50);

/* ── นิติกรเกษียนกลับให้ ผอ.กลุ่ม ทบทวน ── */
if ($action === 'return') {
    $note = trim($body['note'] ?? '');
    if (mb_strlen($note) < 3) err('กรุณาระบุข้อความเกษียนกลับอย่างน้อย 3 ตัวอักษร', 422);
    addEvent($db, $caseId, 'เกษียนกลับ ผอ.กลุ่ม', $actorName, $moment, $note, 'flag');
    // ปลดล็อกให้ ผอ.กลุ่ม เปลี่ยนนิติกร/แก้ข้อสั่งการได้อีก
    if (in_array('lawyer_sent_at', $caseCols)) {
        $db->prepare("UPDATE cases SET lawyer_sent_at = NULL WHERE id = ?")->execute([$caseId]);
    }
    notifyUser($db, groupLeaderUserId($db, $case['assigned_group'] ?? null), $caseId, 'return',
        "↩️ นิติกรเกษียนกลับเพื่อทบทวน: {$subjShort}", $note);
    audit('lawyer_return', $caseId, $note);
    json_out(['ok' => true]);
}

/* ── นิติกรรายงานผลการดำเนินการ ── */
if ($action === 'report') {
    $note      = trim($body['note'] ?? '');
    $fileCount = (int)($body['file_count'] ?? 0);
    if ($note === '' && $fileCount < 1) err('กรุณาพิมพ์รายงานผลหรือแนบไฟล์อย่างน้อย 1 อย่าง', 422);
    $detail = $note !== '' ? $note : '(รายงานผลโดยแนบไฟล์)';
    if ($fileCount > 0) $detail .= "\n📎 แนบไฟล์รายงาน {$fileCount} ไฟล์ (ดูในคลังสำนวน)";
    addEvent($db, $caseId, 'รายงานผลการดำเนินการ', $actorName, $moment, $detail, 'checkCircle');
    $db->prepare("UPDATE cases SET status='reporting' WHERE id = ? AND status NOT IN ('closed','rejected')")->execute([$caseId]);
    notifyUser($db, groupLeaderUserId($db, $case['assigned_group'] ?? null), $caseId, 'report',
        "📄 นิติกรรายงานผลการดำเนินการ: {$subjShort}", $detail);
    audit('lawyer_report', $caseId, $detail);
    json_out(['ok' => true]);
}

/* ── ผอ.กลุ่ม เกษียนรายงานผลถึง ผอ.สำนัก ผ่านธุรการ ── */
if ($action === 'forward_up') {
    $note = trim($body['note'] ?? '');
    if (mb_strlen($note) < 3) err('กรุณาระบุข้อความอย่างน้อย 3 ตัวอักษร', 422);
    addEvent($db, $caseId, 'เกษียนรายงานถึง ผอ.สำนัก', $actorName, $moment, $note, 'flag');
    // แจ้งธุรการเจ้าของเรื่อง (clerk = user ที่ผูกกับ assignee_id)
    if (!empty($case['assignee_id'])) {
        $cu = $db->prepare("SELECT id FROM users WHERE officer_id = ? AND active = 1 LIMIT 1");
        $cu->execute([$case['assignee_id']]);
        notifyUser($db, $cu->fetchColumn() ?: null, $caseId, 'forward_up',
            "📤 ผอ.กลุ่มเกษียนรายงานผลถึง ผอ.สำนัก (ผ่านท่าน): {$subjShort}", $note);
    }
    // แจ้ง ผอ.สำนัก ทุกคน
    foreach ($db->query("SELECT id FROM users WHERE role='dir_admin' AND active=1")->fetchAll(PDO::FETCH_COLUMN) as $daId) {
        notifyUser($db, $daId, $caseId, 'forward_up', "📤 รายงานผลจากกลุ่ม (ผ่านธุรการ): {$subjShort}", $note);
    }
    audit('forward_report_up', $caseId, $note);
    json_out(['ok' => true]);
}

err('action ไม่ถูกต้อง', 400);
