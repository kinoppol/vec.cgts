<?php
/* ============================================================
   api/case_memos.php — รวมข้อความเกษียนทุกขั้นตอนของสำนวน (เรียงตามเวลา)
   GET ?case_id=
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$db     = getDB();
$caseId = trim($_GET['case_id'] ?? '');
if (!$caseId) err('ต้องระบุ case_id');

$cs = $db->prepare("SELECT subject, created_at, created_by FROM cases WHERE id = ?");
$cs->execute([$caseId]);
$case = $cs->fetch();
if (!$case) err('ไม่พบสำนวน', 404);

$memos = [];

/* 1) รับเรื่องเข้าระบบ */
$creatorName = 'ประชาชน (ช่องทางออนไลน์)';
$creatorTitle = 'ผู้ยื่นเรื่อง';
$creatorRole = null;
if (!empty($case['created_by'])) {
    $u = $db->prepare("SELECT display_name, job_title, role FROM users WHERE id = ?");
    $u->execute([$case['created_by']]);
    if ($cu = $u->fetch()) {
        $creatorName  = $cu['display_name'];
        $creatorTitle = $cu['job_title'];
        $creatorRole  = $cu['role'];
    }
}
$memos[] = [
    'when'        => $case['created_at'],
    'kind'        => 'รับเรื่องเข้าระบบ',
    'actor_name'  => $creatorName,
    'actor_title' => $creatorTitle,
    'actor_role'  => $creatorRole,
    'text'        => 'รับเรื่อง “' . $case['subject'] . '” เข้าสู่ระบบ',
    'extra'       => null,
];

/* 2) เกษียนเสนอ ผอ.สำนัก + การพิจารณาของ ผอ.สำนัก */
try {
    $pq = $db->prepare("
        SELECT p.propose_note, p.created_at, p.review_note, p.reviewed_at, p.status,
               u.display_name AS p_name, u.job_title AS p_title, u.role AS p_role,
               r.display_name AS r_name, r.job_title AS r_title, r.role AS r_role
        FROM case_task_proposals p
        LEFT JOIN users u ON u.id = p.proposed_by
        LEFT JOIN users r ON r.id = p.reviewed_by
        WHERE p.case_id = ? ORDER BY p.created_at");
    $pq->execute([$caseId]);
    foreach ($pq->fetchAll() as $p) {
        if (!empty($p['propose_note'])) {
            $memos[] = [
                'when' => $p['created_at'], 'kind' => 'เกษียนเสนอ ผอ.สำนัก',
                'actor_name' => $p['p_name'], 'actor_title' => $p['p_title'], 'actor_role' => $p['p_role'],
                'text' => $p['propose_note'], 'extra' => null,
            ];
        }
        if (!empty($p['reviewed_at']) && !empty($p['review_note'])) {
            $memos[] = [
                'when' => $p['reviewed_at'], 'kind' => 'ข้อสั่งการ ผอ.สำนัก',
                'actor_name' => $p['r_name'], 'actor_title' => $p['r_title'], 'actor_role' => $p['r_role'],
                'text' => $p['review_note'], 'extra' => null,
            ];
        }
    }
} catch (Throwable) {}

/* 3) เกษียนเสนอ ผอ.กลุ่ม (เลขรับภายในกลุ่ม) */
try {
    $gq = $db->prepare("
        SELECT gr.note, gr.recv_no, gr.created_at,
               u.display_name AS u_name, u.job_title AS u_title, u.role AS u_role
        FROM group_receipts gr LEFT JOIN users u ON u.id = gr.created_by
        WHERE gr.case_id = ? ORDER BY gr.created_at");
    $gq->execute([$caseId]);
    foreach ($gq->fetchAll() as $g) {
        $memos[] = [
            'when' => $g['created_at'], 'kind' => 'เกษียนเสนอ ผอ.กลุ่ม',
            'actor_name' => $g['u_name'], 'actor_title' => $g['u_title'], 'actor_role' => $g['u_role'],
            'text' => $g['note'] ?: '(ไม่มีข้อความ)', 'extra' => $g['recv_no'],
        ];
    }
} catch (Throwable) {}

/* 4) ข้อสั่งการถึงนิติกรผู้ดำเนินการ (ผอ.กลุ่ม) */
try {
    $ccols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('lawyer_note', $ccols)) {
        $lq = $db->prepare("
            SELECT c.lawyer_note, c.lawyer_note_at, c.lawyer_sent_at,
                   u.display_name AS u_name, u.job_title AS u_title, u.role AS u_role,
                   o.name AS lawyer_name
            FROM cases c
            LEFT JOIN users    u ON u.id = c.lawyer_note_by
            LEFT JOIN officers o ON o.id = c.lawyer_id
            WHERE c.id = ?");
        $lq->execute([$caseId]);
        $l = $lq->fetch();
        if ($l && !empty($l['lawyer_note'])) {
            $extra = $l['lawyer_name'] ? ('มอบหมาย: ' . $l['lawyer_name']) : null;
            if (!empty($l['lawyer_sent_at'])) $extra = ($extra ? $extra . ' · ' : '') . 'ส่งเรื่องแล้ว';
            $memos[] = [
                'when'        => $l['lawyer_note_at'] ?: $case['created_at'],
                'kind'        => 'ข้อสั่งการ ผอ.กลุ่ม',
                'actor_name'  => $l['u_name'] ?: 'ผอ.กลุ่ม',
                'actor_title' => $l['u_title'], 'actor_role' => $l['u_role'],
                'text'        => $l['lawyer_note'],
                'extra'       => $extra,
            ];
        }
    }
} catch (Throwable) {}

/* 5) เกษียนกลับ / รายงานผล / เกษียนรายงานถึง ผอ.สำนัก (จาก case_events) */
try {
    $eq = $db->prepare("SELECT title, actor, detail, started_at
        FROM case_events
        WHERE case_id = ? AND title IN ('เกษียนกลับ ผอ.กลุ่ม','รายงานผลการดำเนินการ','ผอ.กลุ่มส่งรายงานให้ธุรการ','ธุรการเกษียนรายงานถึง ผอ.สำนัก')
          AND detail IS NOT NULL AND detail <> ''
        ORDER BY id");
    $eq->execute([$caseId]);
    foreach ($eq->fetchAll() as $e) {
        $memos[] = [
            'when' => $e['started_at'] ?: $case['created_at'],
            'kind' => $e['title'],
            'actor_name' => $e['actor'] ?: '—', 'actor_title' => null, 'actor_role' => null,
            'text' => $e['detail'], 'extra' => null,
        ];
    }
} catch (Throwable) {}

/* เรียงตามเวลา (เก่า→ใหม่) */
usort($memos, fn($a, $b) => strcmp($a['when'] ?? '', $b['when'] ?? ''));

json_out($memos);
