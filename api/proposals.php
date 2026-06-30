<?php
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

/* ====== GET /api/proposals.php — รายการข้อเสนอรอการอนุมัติ (dir_legal / admin) ====== */
if ($method === 'GET') {
    if (!in_array($auth['role'], ['admin','dir_legal','dir_admin'], true)) err('Forbidden', 403);

    $caseId = trim($_GET['case_id'] ?? '');
    // lawyer_id อาจยังไม่ได้ migrate
    $caseCols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
    $lawyerSel = in_array('lawyer_id', $caseCols) ? 'c.lawyer_id AS case_lawyer' : 'NULL AS case_lawyer';
    $sql = "
        SELECT p.id, p.case_id, p.from_task_no, p.to_task_no,
               p.proposed_officer, p.proposed_groups, p.proposed_personnel, p.proposed_by,
               p.propose_note, p.status, p.final_officer,
               p.reviewed_by, p.review_note, p.created_at, p.reviewed_at,
               u.display_name AS proposed_by_name,
               o.name AS proposed_officer_name,
               c.subject AS case_subject,
               c.assignee_id AS case_assignee,
               $lawyerSel
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
    // proposed_groups: array of group name strings
    $proposedGroups     = (isset($b['proposed_groups']) && is_array($b['proposed_groups']) && count($b['proposed_groups']))
                          ? json_encode($b['proposed_groups'], JSON_UNESCAPED_UNICODE) : null;
    // proposed_personnel: array of officer id strings
    $proposedPersonnel  = (isset($b['proposed_personnel']) && is_array($b['proposed_personnel']) && count($b['proposed_personnel']))
                          ? json_encode($b['proposed_personnel'], JSON_UNESCAPED_UNICODE) : null;

    if (!$caseId) err('ต้องระบุ case_id');

    // ตรวจว่าสำนวนยังไม่ได้รับมอบหมาย
    $row = $db->prepare('SELECT id, assignee_id FROM cases WHERE id=?');
    $row->execute([$caseId]);
    $case = $row->fetch();
    if (!$case) err('ไม่พบสำนวน', 404);
    if ($case['assignee_id']) err('สำนวนนี้มีผู้รับผิดชอบแล้ว ไม่สามารถนำเสนอได้');

    // ตรวจ columns ที่อาจยังไม่ได้ migrate (graceful fallback)
    $existCols = $db->query("SHOW COLUMNS FROM case_task_proposals")->fetchAll(PDO::FETCH_COLUMN);
    $hasPropGroups    = in_array('proposed_groups',    $existCols);
    $hasPropPersonnel = in_array('proposed_personnel', $existCols);

    // ยกเลิก proposal เก่าของสำนวนนี้ (ถ้ามี)
    $db->prepare("UPDATE case_task_proposals SET status='changed' WHERE case_id=? AND from_task_no=0 AND status='pending'")
       ->execute([$caseId]);

    $cols   = ['case_id','from_task_no','to_task_no','proposed_officer','proposed_by','propose_note'];
    $vals   = [$caseId, 0, 1, $proposedOfficer, (int)$auth['id'], $note];
    if ($hasPropGroups)    { $cols[] = 'proposed_groups';    $vals[] = $proposedGroups; }
    if ($hasPropPersonnel) { $cols[] = 'proposed_personnel'; $vals[] = $proposedPersonnel; }

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    try {
        $db->prepare("INSERT INTO case_task_proposals (" . implode(',', $cols) . ") VALUES ($placeholders)")
           ->execute($vals);
    } catch (PDOException $e) {
        err('บันทึกไม่สำเร็จ: ' . $e->getMessage());
    }

    $propId = (int)$db->lastInsertId();
    if (!$propId) err('บันทึกไม่สำเร็จ: ไม่ได้รับ ID กลับมา');

    // เปลี่ยน status เป็น screening เพื่อบอกว่ารอ dir_admin พิจารณา
    $db->prepare("UPDATE cases SET status='screening' WHERE id=? AND status IN ('received','screening')")
       ->execute([$caseId]);

    // เริ่ม SLA ขั้น "เสนอ ผอ.สำนัก" อัตโนมัติ
    startSlaStep($db, $caseId, 'propose_dir');

    audit('propose_case', $caseId, "นำเสนอ officer={$proposedOfficer}");
    json_out(['id' => $propId, 'ok' => true], 201);
}

/* ====== PATCH /api/proposals.php?id= — dir_legal อนุมัติ / เปลี่ยน ====== */
if ($method === 'PATCH') {
    if (!in_array($auth['role'], ['admin','dir_legal','dir_admin'], true)) err('Forbidden', 403);

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
    $finalGroup   = trim($b['final_group']   ?? '') ?: null;
    $reviewNote   = trim($b['review_note']   ?? '') ?: null;
    $newStatus    = ($action === 'change') ? 'changed' : 'approved';

    // ตรวจว่ามีคอลัมน์ final_group แล้วหรือไม่
    $propCols = $db->query("SHOW COLUMNS FROM case_task_proposals")->fetchAll(PDO::FETCH_COLUMN);
    $hasFinalGroup = in_array('final_group', $propCols);

    // อัปเดต proposal
    if ($hasFinalGroup) {
        $db->prepare("
            UPDATE case_task_proposals
            SET status=?, final_officer=?, final_group=?, reviewed_by=?, review_note=?, reviewed_at=NOW()
            WHERE id=?
        ")->execute([$newStatus, $finalOfficer, $finalGroup, (int)$auth['id'], $reviewNote, $propId]);
    } else {
        $db->prepare("
            UPDATE case_task_proposals
            SET status=?, final_officer=?, reviewed_by=?, review_note=?, reviewed_at=NOW()
            WHERE id=?
        ")->execute([$newStatus, $finalOfficer, (int)$auth['id'], $reviewNote, $propId]);
    }

    // มอบหมายสำนวนจริง
    if ($finalOfficer) {
        $caseCols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
        $hasAssignedGroup = in_array('assigned_group', $caseCols);
        if ($hasAssignedGroup) {
            $db->prepare("UPDATE cases SET assignee_id=?, assigned_group=?, status='assigned' WHERE id=?")->execute([$finalOfficer, $finalGroup, $prop['case_id']]);
        } else {
            $db->prepare("UPDATE cases SET assignee_id=?, status='assigned' WHERE id=?")->execute([$finalOfficer, $prop['case_id']]);
        }

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

    // เริ่ม SLA ขั้น "มอบหมายนิติกร" อัตโนมัติ
    if ($finalOfficer) {
        startSlaStep($db, $prop['case_id'], 'assign');
    }

    audit('approve_proposal', $prop['case_id'], "proposal={$propId} final={$finalOfficer}");
    json_out(['ok' => true, 'case_id' => $prop['case_id']]);
}

err('Method not allowed', 405);
