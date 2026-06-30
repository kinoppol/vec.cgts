<?php
/* ============================================================
   api/group_receipt.php — เลขรับภายในกลุ่ม (เกษียนถึงหัวหน้ากลุ่ม)
   GET  ?case_id=&action=preview — ดูเลขรับที่เรื่องจะได้รับ + ข้อมูลกลุ่ม
   POST  {case_id, note}         — ออกเลขรับ + บันทึกการเกษียน
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/* หา group record จากชื่อกลุ่มที่ได้รับมอบหมายของ case */
function groupForCase(PDO $db, string $caseId): array {
    $cs = $db->prepare("SELECT assigned_group FROM cases WHERE id = ?");
    $cs->execute([$caseId]);
    $row = $cs->fetch();
    if (!$row) err('ไม่พบสำนวน', 404);
    $gname = trim($row['assigned_group'] ?? '');
    if ($gname === '') err('เรื่องนี้ยังไม่ได้ระบุกลุ่มที่ได้รับมอบหมาย');

    $hasPfx = in_array('recv_prefix', $db->query("SHOW COLUMNS FROM groups")->fetchAll(PDO::FETCH_COLUMN), true);
    $sel = $hasPfx ? 'g.recv_prefix' : "NULL AS recv_prefix";
    $g = $db->prepare("SELECT g.id, g.name, g.leader_id, $sel, u.display_name AS leader_name
                       FROM groups g LEFT JOIN users u ON u.id = g.leader_id WHERE g.name = ? LIMIT 1");
    $g->execute([$gname]);
    $grp = $g->fetch();
    if (!$grp) err('ไม่พบกลุ่ม "' . $gname . '" ในระบบ');
    return $grp;
}

function nextGroupSeq(PDO $db, int $groupId, int $year): int {
    $s = $db->prepare("SELECT COALESCE(MAX(seq),0)+1 FROM group_receipts WHERE group_id = ? AND year = ?");
    $s->execute([$groupId, $year]);
    return (int)$s->fetchColumn();
}

function fmtRecvNo(?string $prefix, int $year, int $seq): string {
    $pfx = preg_replace('/[^A-Za-z0-9ก-๙]/u', '', $prefix ?: 'GRP');
    return $pfx . '-' . $year . '-' . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
}

/* ── GET preview ── */
if ($method === 'GET' && ($_GET['action'] ?? '') === 'preview') {
    $caseId = trim($_GET['case_id'] ?? '');
    if (!$caseId) err('ต้องระบุ case_id');
    $grp  = groupForCase($db, $caseId);
    $year = (int)date('Y') + 543;
    $seq  = nextGroupSeq($db, (int)$grp['id'], $year);
    json_out([
        'group_id'    => (int)$grp['id'],
        'group_name'  => $grp['name'],
        'leader_name' => $grp['leader_name'],
        'prefix'      => $grp['recv_prefix'],
        'year'        => $year,
        'next_seq'    => $seq,
        'recv_no'     => fmtRecvNo($grp['recv_prefix'], $year, $seq),
        'has_prefix'  => !empty($grp['recv_prefix']),
    ]);
}

/* ── POST issue ── */
if ($method === 'POST') {
    $b      = json_decode(file_get_contents('php://input'), true) ?? [];
    $caseId = trim($b['case_id'] ?? '');
    $note   = trim($b['note'] ?? '') ?: null;
    if (!$caseId) err('ต้องระบุ case_id');

    $grp  = groupForCase($db, $caseId);
    $year = (int)date('Y') + 543;

    // ออกเลขรับแบบกันชนกัน (ลองซ้ำถ้า unique ชน)
    $recvNo = null; $seq = 0;
    for ($try = 0; $try < 5; $try++) {
        $seq    = nextGroupSeq($db, (int)$grp['id'], $year);
        $recvNo = fmtRecvNo($grp['recv_prefix'], $year, $seq);
        try {
            $db->prepare("INSERT INTO group_receipts (group_id, year, seq, recv_no, case_id, note, created_by)
                          VALUES (?,?,?,?,?,?,?)")
               ->execute([(int)$grp['id'], $year, $seq, $recvNo, $caseId, $note, (int)$auth['id']]);
            break;
        } catch (PDOException $e) {
            if ($try === 4) err('ออกเลขรับไม่สำเร็จ: ' . $e->getMessage());
            $recvNo = null; // ชน — ลองใหม่
        }
    }

    // บันทึกเลขรับลง case (ถ้ามีคอลัมน์)
    $hasCol = in_array('group_recv_no', $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN), true);
    if ($hasCol) {
        $db->prepare("UPDATE cases SET group_recv_no = ? WHERE id = ?")->execute([$recvNo, $caseId]);
    }

    // event ในไทม์ไลน์
    $thYear = date('Y') + 543;
    $maxOrd = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM case_events WHERE case_id=?');
    $maxOrd->execute([$caseId]);
    $ord = $maxOrd->fetchColumn();
    $db->prepare("INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$caseId, 'เกษียนเรื่องถึงผู้อำนวยการกลุ่ม', 'เจ้าหน้าที่ผู้รับผิดชอบ',
           date('j') . ' ' . ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')] . ' ' . $thYear,
           "เลขรับ {$recvNo} — เสนอ ผอ.{$grp['name']}" . ($note ? " · {$note}" : ''),
           'done', 'flag', $ord]);

    // แจ้งเตือนหัวหน้ากลุ่ม
    if (!empty($grp['leader_id'])) {
        $cStmt = $db->prepare('SELECT subject FROM cases WHERE id = ?');
        $cStmt->execute([$caseId]);
        $subj = mb_substr($cStmt->fetchColumn() ?: $caseId, 0, 60);
        $db->prepare("INSERT INTO notifications (user_id, case_id, notif_type, title, body) VALUES (?,?,?,?,?)")
           ->execute([(int)$grp['leader_id'], $caseId, 'assigned',
               "📋 เกษียนเรื่องถึงท่าน: {$subj}",
               "สำนวน {$caseId} เลขรับ {$recvNo} รอการพิจารณามอบหมาย"]);
    }

    audit('group_receipt_issue', $caseId, "recv_no={$recvNo} group={$grp['id']}");
    json_out(['ok' => true, 'recv_no' => $recvNo, 'seq' => $seq, 'group_name' => $grp['name']]);
}

err('Method not allowed', 405);
