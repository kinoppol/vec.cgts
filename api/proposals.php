<?php
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

/* ====== GET /api/proposals.php — รายการข้อเสนอรอการอนุมัติ (dir_legal / admin) ====== */
if ($method === 'GET') {
    if (!in_array($auth['role'], ['admin','dir_legal'], true)) err('Forbidden', 403);

    $caseId = trim($_GET['case_id'] ?? '');
    $sql = "
        SELECT p.*, u.display_name AS proposed_by_name,
               o.name AS proposed_officer_name,
               c.subject AS case_subject
        FROM case_task_proposals p
        LEFT JOIN users    u ON u.id = p.proposed_by
        LEFT JOIN officers o ON o.id = p.proposed_officer
        LEFT JOIN cases    c ON c.id = p.case_id
        WHERE p.from_task_no = 0 AND p.status = 'pending'
    ";
    $params = [];
    if ($caseId !== '') {
        $sql .= ' AND p.case_id = ?';
        $params[] = $caseId;
    }
    $sql .= ' ORDER BY p.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    json_out($stmt->fetchAll());
}

/* ====== POST /api/proposals.php — หัวหน้าธุรการนำเสนอ ====== */
if ($method === 'POST') {
    if ($auth['role'] !== 'head_secretary' && $auth['role'] !== 'admin') err('Forbidden', 403);

    $b               = json_decode(file_get_contents('php://input'), true) ?? [];
    $caseId          = trim($b['case_id'] ?? '');
    $proposedOfficer = trim($b['proposed_officer'] ?? '') ?: null;
    $note            = trim($b['note'] ?? '') ?: null;

    if (!$caseId) err('ต้องระบุ case_id');

    // ตรวจว่าสำนวนยังไม่ได้รับมอบหมาย
    $row = $db->prepare('SELECT id, assignee_id FROM cases WHERE id=?');
    $row->execute([$caseId]);
    $case = $row->fetch();
    if (!$case) err('ไม่พบสำนวน', 404);
    if ($case['assignee_id']) err('สำนวนนี้มีผู้รับผิดชอบแล้ว ไม่สามารถนำเสนอได้');

    // ยกเลิก proposal เก่าของสำนวนนี้ (ถ้ามี)
    $db->prepare("UPDATE case_task_proposals SET status='changed' WHERE case_id=? AND from_task_no=0 AND status='pending'")
       ->execute([$caseId]);

    // สร้าง proposal ใหม่
    $db->prepare("
        INSERT INTO case_task_proposals (case_id, from_task_no, to_task_no, proposed_officer, proposed_by, propose_note)
        VALUES (?, 0, 1, ?, ?, ?)
    ")->execute([$caseId, $proposedOfficer, (int)$auth['id'], $note]);

    $propId = (int)$db->lastInsertId();
    audit('propose_case', $caseId, "นำเสนอ officer={$proposedOfficer}");
    json_out(['id' => $propId, 'ok' => true], 201);
}

/* ====== PATCH /api/proposals.php?id= — dir_legal อนุมัติ / เปลี่ยน ====== */
if ($method === 'PATCH') {
    if (!in_array($auth['role'], ['admin','dir_legal'], true)) err('Forbidden', 403);

    $propId = (int)($_GET['id'] ?? 0);
    if (!$propId) err('ต้องระบุ id');

    $b      = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $b['action'] ?? 'approve'; // 'approve' | 'change'

    $prop = $db->prepare('SELECT * FROM case_task_proposals WHERE id=?');
    $prop->execute([$propId]);
    $prop = $prop->fetch();
    if (!$prop || $prop['from_task_no'] !== 0) err('ไม่พบข้อเสนอ', 404);
    if ($prop['status'] !== 'pending') err('ข้อเสนอนี้ถูกดำเนินการแล้ว');

    $finalOfficer = trim($b['final_officer'] ?? '') ?: $prop['proposed_officer'];
    $reviewNote   = trim($b['review_note'] ?? '') ?: null;
    $newStatus    = ($action === 'change') ? 'changed' : 'approved';

    // อัปเดต proposal
    $db->prepare("
        UPDATE case_task_proposals
        SET status=?, final_officer=?, reviewed_by=?, review_note=?, reviewed_at=NOW()
        WHERE id=?
    ")->execute([$newStatus, $finalOfficer, (int)$auth['id'], $reviewNote, $propId]);

    // มอบหมายสำนวนจริง
    if ($finalOfficer) {
        $db->prepare("UPDATE cases SET assignee_id=?, status='assigned' WHERE id=?")->execute([$finalOfficer, $prop['case_id']]);

        // บันทึก event
        $thYear = date('Y') + 543;
        $oname = $db->prepare('SELECT name FROM officers WHERE id=?');
        $oname->execute([$finalOfficer]);
        $oname = $oname->fetchColumn() ?: $finalOfficer;

        $maxOrd = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM case_events WHERE case_id=?');
        $maxOrd->execute([$prop['case_id']]);
        $ord = $maxOrd->fetchColumn();

        $db->prepare("INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([
               $prop['case_id'], 'มอบหมายผู้รับผิดชอบ', 'ผอ.กลุ่มนิติการ',
               date('j') . ' ' . ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')] . ' ' . $thYear,
               "อนุมัติมอบหมาย {$oname} (เสนอโดยหัวหน้าธุรการ)" . ($reviewNote ? " · {$reviewNote}" : ''),
               'done', 'gavel', $ord,
           ]);
    }

    audit('approve_proposal', $prop['case_id'], "proposal={$propId} final={$finalOfficer}");
    json_out(['ok' => true, 'case_id' => $prop['case_id']]);
}

err('Method not allowed', 405);
