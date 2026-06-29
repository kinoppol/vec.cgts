<?php
require_once __DIR__ . '/_common.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');
$id     = (int)($_GET['id'] ?? 0);
$pid    = (int)($_GET['proposal_id'] ?? 0);

$auth = require_auth();
$db   = getDB();

/* ---------- helper: โหลด tasks+proposals ---------- */
function loadTasks(string $cid, PDO $db): array {
    $t = $db->prepare('
        SELECT t.*, o.name AS officer_name, o.init AS officer_init,
               u.display_name AS completed_by_name
        FROM case_tasks t
        LEFT JOIN officers o ON o.id = t.officer_id
        LEFT JOIN users    u ON u.id = t.completed_by
        WHERE t.case_id = ? ORDER BY t.task_no
    ');
    $t->execute([$cid]);

    $p = $db->prepare('
        SELECT p.*,
               o1.name AS proposed_name, o1.init AS proposed_init,
               o2.name AS final_name,    o2.init AS final_init,
               u1.display_name AS proposed_by_name,
               u2.display_name AS reviewed_by_name
        FROM case_task_proposals p
        LEFT JOIN officers o1 ON o1.id = p.proposed_officer
        LEFT JOIN officers o2 ON o2.id = p.final_officer
        LEFT JOIN users    u1 ON u1.id = p.proposed_by
        LEFT JOIN users    u2 ON u2.id = p.reviewed_by
        WHERE p.case_id = ? ORDER BY p.created_at DESC
    ');
    $p->execute([$cid]);
    return ['tasks' => $t->fetchAll(), 'proposals' => $p->fetchAll()];
}

/* ====== GET ?case_id=xxx ====== */
if ($method === 'GET') {
    $cid = trim($_GET['case_id'] ?? '');
    if (!$cid) err('ต้องระบุ case_id', 400);
    json_out(loadTasks($cid, $db));
}

/* ====== POST ?action=init — สร้างรายการงาน 5 งาน ====== */
if ($method === 'POST' && $action === 'init') {
    if (!in_array($auth['role'], ['admin','dir_legal','dir_admin'])) err('ไม่มีสิทธิ์', 403);

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $cid  = trim($body['case_id']    ?? '');
    $oid  = trim($body['officer_id'] ?? '');
    $due  = trim($body['due_date']   ?? '') ?: null;
    if (!$cid || !$oid) err('ต้องระบุ case_id และ officer_id', 400);

    // ตรวจว่ายังไม่มี tasks
    $cnt = $db->prepare('SELECT COUNT(*) FROM case_tasks WHERE case_id = ?');
    $cnt->execute([$cid]);
    if ($cnt->fetchColumn() > 0) err('มีรายการงานอยู่แล้ว', 409);

    $names = ['รับเรื่อง','ตรวจสอบเอกสาร','ทำหนังสือ/จัดทำสำนวน','เสนอผู้บังคับบัญชา','ออกคำสั่ง/แจ้งผล'];
    $ins   = $db->prepare('INSERT INTO case_tasks (case_id,task_no,task_name,officer_id,start_date,due_date,status) VALUES (?,?,?,?,CURDATE(),?,?)');
    foreach ($names as $i => $name) {
        $no = $i + 1;
        $ins->execute([$cid, $no, $name, $no===1?$oid:null, $no===1?$due:null, $no===1?'in_progress':'pending']);
    }

    audit('init_tasks', $cid, "สร้างรายการงาน มอบหมาย task 1 ให้ {$oid}");
    json_out(loadTasks($cid, $db));
}

/* ====== PATCH ?id=xxx — อัปเดต progress / note ====== */
if ($method === 'PATCH' && $id > 0) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $task = $db->prepare('SELECT * FROM case_tasks WHERE id = ?');
    $task->execute([$id]);
    $t = $task->fetch();
    if (!$t) err('ไม่พบงาน', 404);

    $sets = []; $params = [];
    if (array_key_exists('progress', $body)) { $sets[] = 'progress=?'; $params[] = max(0,min(100,(int)$body['progress'])); }
    if (array_key_exists('note', $body))     { $sets[] = 'note=?';     $params[] = $body['note']; }
    if (!$sets) err('ไม่มีข้อมูลอัปเดต', 400);

    $params[] = $id;
    $db->prepare('UPDATE case_tasks SET '.implode(',', $sets).' WHERE id = ?')->execute($params);
    json_out(loadTasks($t['case_id'], $db));
}

/* ====== POST ?action=complete&id=xxx — ทำงานเสร็จ + เสนอมอบหมายต่อ ====== */
if ($method === 'POST' && $action === 'complete' && $id > 0) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $task = $db->prepare('SELECT * FROM case_tasks WHERE id = ?');
    $task->execute([$id]);
    $t = $task->fetch();
    if (!$t) err('ไม่พบงาน', 404);
    if ($t['status'] !== 'in_progress') err('งานนี้ไม่ได้อยู่ระหว่างดำเนินการ', 422);

    // mark done
    $db->prepare('UPDATE case_tasks SET status=\'done\',progress=100,completed_at=NOW(),completed_by=? WHERE id=?')
       ->execute([$auth['id'], $id]);

    if ((int)$t['task_no'] >= 5) {
        audit('complete_task', $t['case_id'], "ทำงานที่ {$t['task_no']} เสร็จสิ้น (งานสุดท้าย)");
        json_out(loadTasks($t['case_id'], $db));
    }

    // สร้าง proposal
    $propOfficer = trim($body['proposed_officer'] ?? '');
    $nextDue     = trim($body['next_due_date']    ?? '') ?: null;
    $propNote    = trim($body['note']             ?? '') ?: null;
    if (!$propOfficer) err('ต้องระบุผู้รับผิดชอบงานถัดไป', 400);

    $db->prepare('
        INSERT INTO case_task_proposals
          (case_id,from_task_no,to_task_no,proposed_officer,proposed_by,propose_note,next_due_date)
        VALUES (?,?,?,?,?,?,?)
    ')->execute([$t['case_id'], $t['task_no'], $t['task_no']+1, $propOfficer, $auth['id'], $propNote, $nextDue]);

    audit('complete_task', $t['case_id'], "งานที่ {$t['task_no']} เสร็จ เสนอ {$propOfficer} รับงานที่ ".($t['task_no']+1));
    json_out(loadTasks($t['case_id'], $db));
}

/* ====== POST ?action=approve&proposal_id=xxx — อนุมัติ/เปลี่ยนแปลง ====== */
if ($method === 'POST' && $action === 'approve' && $pid > 0) {
    if (!in_array($auth['role'], ['admin','dir_legal','dir_admin'])) err('ไม่มีสิทธิ์', 403);

    $pStmt = $db->prepare('SELECT * FROM case_task_proposals WHERE id = ?');
    $pStmt->execute([$pid]);
    $p = $pStmt->fetch();
    if (!$p)                   err('ไม่พบการเสนอ', 404);
    if ($p['status'] !== 'pending') err('การเสนอนี้ดำเนินการแล้ว', 409);

    $body         = json_decode(file_get_contents('php://input'), true) ?? [];
    $finalOfficer = trim($body['final_officer'] ?? $p['proposed_officer']);
    $reviewNote   = trim($body['review_note']   ?? '') ?: null;
    $newStatus    = ($finalOfficer !== $p['proposed_officer']) ? 'changed' : 'approved';

    $db->prepare('UPDATE case_task_proposals SET status=?,final_officer=?,reviewed_by=?,review_note=?,reviewed_at=NOW() WHERE id=?')
       ->execute([$newStatus, $finalOfficer, $auth['id'], $reviewNote, $pid]);

    // activate task ถัดไป
    $db->prepare('UPDATE case_tasks SET officer_id=?,start_date=CURDATE(),due_date=?,status=\'in_progress\' WHERE case_id=? AND task_no=?')
       ->execute([$finalOfficer, $p['next_due_date'], $p['case_id'], $p['to_task_no']]);

    audit('approve_proposal', $p['case_id'], "อนุมัติงานที่ {$p['to_task_no']} ให้ {$finalOfficer} ({$newStatus})");
    json_out(loadTasks($p['case_id'], $db));
}

err('Method not allowed', 405);
