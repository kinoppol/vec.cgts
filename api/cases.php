<?php
require_once __DIR__ . '/_common.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = trim($_GET['id'] ?? '');

/* ====== helpers ====== */

/**
 * สร้าง/ลบ calendar_event ประเภท sla_deadline สำหรับสำนวนที่ระบุ
 * เรียกหลังจาก UPDATE cases (assignee หรือ status เปลี่ยน)
 */
function syncSlaCalEvent(PDO $db, string $caseId, int $createdBy): void {
    // ตรวจสอบ / เพิ่ม sla_deadline เข้า ENUM ถ้ายังไม่มี
    $colInfo = $db->query("SHOW COLUMNS FROM calendar_events WHERE Field='event_type'")->fetch();
    $enumHasSla = $colInfo && strpos($colInfo['Type'], 'sla_deadline') !== false;
    if (!$enumHasSla) {
        try {
            $db->exec("ALTER TABLE calendar_events MODIFY event_type
                ENUM('meeting','court','investigation','document','committee','sla_deadline') NOT NULL");
        } catch (Throwable) { return; } // ยัง migrate ไม่ได้ — skip
    }
    // ลบ event เก่าเสมอก่อน
    $db->prepare("DELETE FROM calendar_events WHERE event_type='sla_deadline' AND case_id=?")
       ->execute([$caseId]);

    // ดึงข้อมูลสำนวนล่าสุด
    $row = $db->prepare("
        SELECT c.subject, c.track, c.cat, c.received_date,
               c.assignee_id, c.status
        FROM cases c WHERE c.id = ?
    ");
    $row->execute([$caseId]);
    $c = $row->fetch();

    if (!$c || !$c['assignee_id'] || !$c['received_date'] || $c['status'] === 'closed') return;

    // หาจำนวนวัน SLA จาก sla_settings
    $ss = $db->prepare("SELECT days FROM sla_settings WHERE track=? AND cat=? LIMIT 1");
    $ss->execute([$c['track'], $c['cat']]);
    $days = $ss->fetchColumn();
    if (!$days) return;

    $deadline = date('Y-m-d', strtotime($c['received_date'] . " +{$days} days"));
    $title    = 'ครบ SLA: ' . mb_substr($c['subject'], 0, 80);

    $db->prepare("
        INSERT INTO calendar_events (event_type, title, event_date, case_id, officer_id, created_by)
        VALUES ('sla_deadline', ?, ?, ?, ?, ?)
    ")->execute([$title, $deadline, $caseId, $c['assignee_id'], $createdBy]);
}

function buildCase(array $row, PDO $db): array {
    // files
    $fs = $db->prepare('SELECT id, filename AS n, stored_name AS sn, size_label AS s, cls AS c FROM case_files WHERE case_id = ?');
    $fs->execute([$row['id']]);
    $row['files'] = $fs->fetchAll();

    // sla_steps ทั้งหมด (backbone 6 ขั้น)
    $allSteps = $db->query("SELECT * FROM sla_steps WHERE active = 1 ORDER BY sort_order")->fetchAll();
    $stepsMap = [];
    foreach ($allSteps as $s) $stepsMap[$s['step_key']] = $s;

    // events ของสำนวนนี้ (order_items อาจยังไม่ได้ migrate)
    $evCols = $db->query("SHOW COLUMNS FROM case_events")->fetchAll(PDO::FETCH_COLUMN);
    $orderSel = in_array('order_items', $evCols) ? 'order_items' : 'NULL AS order_items';
    $ev = $db->prepare("
        SELECT id, title AS t, actor AS who, moment AS m, detail AS d,
               ev_status AS st, icon AS ic, step_key, started_at, completed_at,
               attachment_name, attachment_path, attachment_size, $orderSel
        FROM case_events WHERE case_id = ? ORDER BY sort_order
    ");
    $ev->execute([$row['id']]);
    $today = new DateTime(date('Y-m-d'));

    // map event by step_key
    $evByStep = [];
    $freeEvents = [];
    foreach ($ev->fetchAll() as $e) {
        if ($e['step_key'] && isset($stepsMap[$e['step_key']])) {
            $evByStep[$e['step_key']] = $e;
        } else {
            $freeEvents[] = $e;
        }
    }

    // สร้าง steps array (backbone + merge event)
    $steps = [];
    foreach ($allSteps as $s) {
        $allowed = (int)$s['days_allowed'];
        $e       = $evByStep[$s['step_key']] ?? null;
        $start   = ($e && $e['started_at'])   ? new DateTime($e['started_at'])   : null;
        $done    = ($e && $e['completed_at']) ? new DateTime($e['completed_at']) : null;
        $used    = $start ? (int)$start->diff($done ?? $today)->days : null;
        $remain  = $used !== null ? $allowed - $used : null;
        $sla     = null;
        if ($remain !== null) {
            if ($remain < 0)                                   $sla = 'r';
            elseif ($remain <= max(1,(int)($allowed * 0.25))) $sla = 'a';
            else                                               $sla = 'g';
        }
        $steps[] = [
            'step_key'     => $s['step_key'],
            'label'        => $s['label'],
            'days_allowed' => $allowed,
            'sort_order'   => (int)$s['sort_order'],
            // event data (null ถ้าไม่มี event link)
            'event_id'     => $e ? (int)$e['id']  : null,
            'ev_status'    => $e ? $e['st']        : 'pending',
            'started_at'   => $e ? $e['started_at']   : null,
            'completed_at' => $e ? $e['completed_at'] : null,
            'actor'           => $e ? $e['who']             : null,
            'detail'          => $e ? $e['d']               : null,
            'moment'          => $e ? $e['m']               : null,
            'attachment_name' => $e ? $e['attachment_name'] : null,
            'attachment_path' => $e ? $e['attachment_path'] : null,
            'attachment_size' => $e ? $e['attachment_size'] : null,
            'order_items'     => ($e && !empty($e['order_items'])) ? json_decode($e['order_items'], true) : null,
            // computed
            'days_used'    => $used,
            'days_remain'  => $remain,
            'step_sla'     => $sla,
        ];
    }
    $row['steps'] = $steps;

    // events ทั่วไป (ไม่มี step_key) + events ที่มี step_key (backward-compat)
    $events = [];
    foreach ($freeEvents as $e) $events[] = $e;
    foreach ($evByStep as $e) {
        $step    = $stepsMap[$e['step_key']];
        $allowed = (int)$step['days_allowed'];
        $start   = $e['started_at']   ? new DateTime($e['started_at'])   : null;
        $done    = $e['completed_at'] ? new DateTime($e['completed_at']) : null;
        $used    = $start ? (int)$start->diff($done ?? $today)->days : null;
        $remain  = $used !== null ? $allowed - $used : null;
        $sla2    = null;
        if ($remain !== null) {
            if ($remain < 0)                                   $sla2 = 'r';
            elseif ($remain <= max(1,(int)($allowed * 0.25))) $sla2 = 'a';
            else                                               $sla2 = 'g';
        }
        $e['step_label'] = $step['label']; $e['step_days_allowed'] = $allowed;
        $e['step_days_used'] = $used; $e['step_days_remain'] = $remain; $e['step_sla'] = $sla2;
        $events[] = $e;
    }
    $row['events'] = $events;

    // normalize field names ให้ตรงกับ list query
    $row['assignee'] = $row['assignee_id'] ?? null;

    // นิติกรผู้ดำเนินการ (ถ้ามี)
    $row['lawyer'] = $row['lawyer_id'] ?? null;
    if (!empty($row['lawyer_id'])) {
        try {
            $lw = $db->prepare('SELECT name, init, job_title, group_name FROM officers WHERE id = ?');
            $lw->execute([$row['lawyer_id']]);
            $lwRow = $lw->fetch();
            if ($lwRow) {
                $row['lawyer_name']  = $lwRow['name'];
                $row['lawyer_init']  = $lwRow['init'];
                $row['lawyer_role']  = $lwRow['job_title'];
                $row['lawyer_group'] = $lwRow['group_name'];
            }
        } catch (Throwable $e) { /* graceful */ }
    }

    // กลุ่มที่ผู้บริหารมอบหมาย — ใช้ assigned_group ถ้ามี
    // ถ้ายังว่าง (เรื่องเก่า / ยังไม่ได้ migrate) → ดึงจาก proposal ที่อนุมัติล่าสุด
    $assignedGroup = $row['assigned_group'] ?? null;
    if (!$assignedGroup) {
        try {
            $pcols = $db->query("SHOW COLUMNS FROM case_task_proposals")->fetchAll(PDO::FETCH_COLUMN);
            $hasFinalGroup = in_array('final_group', $pcols);
            $hasPropGroups = in_array('proposed_groups', $pcols);
            $sel = ['1 AS _x'];
            if ($hasFinalGroup) $sel[] = 'final_group';
            if ($hasPropGroups) $sel[] = 'proposed_groups';
            $pq = $db->prepare("SELECT " . implode(',', $sel) . "
                FROM case_task_proposals
                WHERE case_id=? AND from_task_no=0 AND status IN ('approved','changed')
                ORDER BY reviewed_at DESC, id DESC LIMIT 1");
            $pq->execute([$row['id']]);
            $p = $pq->fetch();
            if ($p) {
                if (!empty($p['final_group'])) {
                    $assignedGroup = $p['final_group'];
                } elseif (!empty($p['proposed_groups'])) {
                    $g = json_decode($p['proposed_groups'], true);
                    if (is_array($g) && count($g)) $assignedGroup = $g[0];
                }
            }
        } catch (Throwable $e) { /* graceful */ }
    }
    $row['assigned_group'] = $assignedGroup;

    // cast types
    $row['anon']     = (bool)$row['anon'];
    $row['progress'] = (int)$row['progress'];

    return $row;
}

function calcSla(?string $dueDate, int $totalDays, DateTime $today, string $status): string {
    // เรื่องที่ปิดแล้วหรือปฏิเสธ ให้ใช้ g เสมอ
    if (in_array($status, ['closed', 'rejected'])) return 'g';
    if (!$dueDate) return 'g';
    $due       = new DateTime($dueDate);
    $remaining = (int)$today->diff($due)->days * ($due >= $today ? 1 : -1);
    if ($remaining < 0) return 'r';
    // amber = เหลือน้อยกว่า 25% ของ total days (อย่างน้อย 2 วัน)
    $amber = max(2, (int)ceil($totalDays * 0.25));
    return $remaining <= $amber ? 'a' : 'g';
}

function nextCaseId(PDO $db): string {
    $year = date('Y') + 543;
    try {
        $pfx     = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_prefix'")->fetchColumn();
        $nextSeq = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_next_seq'")->fetchColumn();
    } catch (Throwable) { $pfx = null; $nextSeq = null; }
    $pfx    = preg_replace('/[^A-Za-z0-9ก-๙]/', '', $pfx ?: 'CMP');
    $prefix = "{$pfx}-{$year}-";

    if ($nextSeq !== null && $nextSeq !== false && (int)$nextSeq > 0) {
        // ใช้เลขที่กำหนด แล้วล้างค่าออก (ใช้ได้ครั้งเดียว)
        $seq = (int)$nextSeq;
        $db->prepare("DELETE FROM app_settings WHERE `key`='case_id_next_seq'")->execute();
    } else {
        // auto-increment จากเลขล่าสุดใน DB
        $stmt = $db->prepare("SELECT id FROM cases WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        $seq  = $last ? ((int)substr($last, -4) + 1) : 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function generateTrackToken(PDO $db): ?string {
    // graceful: คืน null ถ้าคอลัมน์ยังไม่มี
    try {
        $db->query("SELECT track_token FROM cases LIMIT 1");
    } catch (Throwable) { return null; }
    do {
        $tok = strtoupper(bin2hex(random_bytes(5))); // 10 hex chars
        $ex  = $db->prepare("SELECT id FROM cases WHERE track_token=?");
        $ex->execute([$tok]);
    } while ($ex->fetchColumn());
    return $tok;
}

/* ====== GET /api/cases.php?id=xxx — รายละเอียดสำนวน (สาธารณะ: เฉพาะสถานะ) ====== */
if ($method === 'GET' && $id !== '') {
    $db   = getDB();
    // รองรับทั้ง case_id และ track_token (public)
    $isStaffCheck = !empty($_SESSION['user_id']);
    if (!$isStaffCheck && strlen($id) === 10 && ctype_xdigit($id)) {
        // ค้นหาด้วย track_token
        $stmt = $db->prepare('SELECT c.*, o.name AS assignee_name FROM cases c LEFT JOIN officers o ON o.id = c.assignee_id WHERE c.track_token = ?');
        $stmt->execute([strtoupper($id)]);
    } else {
        $stmt = $db->prepare('SELECT c.*, o.name AS assignee_name FROM cases c LEFT JOIN officers o ON o.id = c.assignee_id WHERE c.id = ?');
        $stmt->execute([$id]);
    }
    $row  = $stmt->fetch();
    if (!$row) err('ไม่พบสำนวน', 404);

    // ตรวจสอบสิทธิ์: ถ้าไม่ใช่เจ้าหน้าที่ ให้เห็นเฉพาะสถานะ
    // คำนวณ SLA dynamic สำหรับ case นี้
    $ss = $db->prepare("SELECT days FROM sla_settings WHERE track = ? AND cat = ?");
    $ss->execute([$row['track'], $row['cat']]);
    $slaDays   = (int)($ss->fetchColumn() ?: 30);
    $slaToday  = new DateTime(date('Y-m-d'));
    $row['sla'] = calcSla($row['due_date'], $slaDays, $slaToday, $row['status']);

    $isStaff = !empty($_SESSION['user_id']);
    if (!$isStaff) {
        $emailOk = false;
        $qEmail  = trim($_GET['email'] ?? '');
        if ($qEmail !== '' && $row['contact'] !== null) {
            $emailOk = strtolower($qEmail) === strtolower($row['contact']);
        }
        // ดึง SLA steps สาธารณะ (เฉพาะที่มี started_at หรือ completed_at)
        $pubSteps = $db->prepare(
            "SELECT ce.step_key, ce.started_at, ce.completed_at, ce.ev_status,
                    COALESCE(ss.label, ce.title) AS label,
                    ss.days_allowed AS sla_days, ss.sort_order
             FROM case_events ce
             LEFT JOIN sla_steps ss ON ss.step_key = ce.step_key AND ss.active = 1
             WHERE ce.case_id = ? AND ce.step_key IS NOT NULL
             ORDER BY COALESCE(ss.sort_order, 999), ce.id"
        );
        $pubSteps->execute([$row['id']]);
        json_out([
            'id'          => $row['id'],
            'track_token' => $row['track_token'] ?? null,
            'status'      => $row['status'],
            'channel'     => $row['channel'],
            'received'    => $row['received_date'],
            'sla'         => $row['sla'],
            'subject'     => $row['subject'],
            'email_ok'    => $emailOk,
            'steps'       => $pubSteps->fetchAll(),
        ]);
    }

    audit('view_case', $id);
    json_out(buildCase($row, $db));
}

/* ====== GET /api/cases.php — รายการสำนวน (ต้อง login) ====== */
if ($method === 'GET') {
    $auth = require_auth();
    $db = getDB();

    // ดึง officer_id ของผู้ใช้ปัจจุบัน
    $uInfo = $db->prepare('SELECT officer_id FROM users WHERE id=?');
    $uInfo->execute([$auth['id']]);
    $myOfficerId = $uInfo->fetchColumn();

    // lawyer_id อาจยังไม่ได้ migrate
    $caseColsList = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
    $hasLawyerCol = in_array('lawyer_id', $caseColsList);

    $where  = [];
    $params = [];

    // กรองตาม role
    if ($auth['role'] === 'head_secretary') {
        // หัวหน้าธุรการ: เห็นเฉพาะสำนวนที่ยังไม่ได้มอบหมาย
        $where[] = 'c.assignee_id IS NULL';
    } elseif (in_array($auth['role'], ['officer', 'secretary', 'clerk'], true)) {
        // เจ้าหน้าที่/ธุรการทั่วไป: เห็นสำนวนที่มอบหมายให้ตัวเอง (ในฐานะ clerk ผู้รับผิดชอบ)
        // หรือในฐานะนิติกรผู้ดำเนินการ — แต่เห็นได้เฉพาะเมื่อ clerk กด "เสร็จแล้ว" ในขั้น "มอบหมายนิติกร" (step_key=assign)
        if ($myOfficerId) {
            $cond = ['c.assignee_id = ?'];
            $params[] = $myOfficerId;
            if ($hasLawyerCol) {
                $cond[] = "(c.lawyer_id = ? AND EXISTS (
                    SELECT 1 FROM case_events ce
                    WHERE ce.case_id = c.id AND ce.step_key = 'assign' AND ce.ev_status = 'done'
                ))";
                $params[] = $myOfficerId;
            }
            $where[] = '(' . implode(' OR ', $cond) . ')';
        } else {
            $where[] = '0=1'; // ไม่เชื่อมกับ officer → ไม่เห็นอะไร
        }
    }
    // admin, dir_legal, dir_admin, deputy_secretary, secretary ระดับสูง: เห็นทุกสำนวน

    if (!empty($_GET['track'])) {
        $where[] = 'c.track = ?';
        $params[] = $_GET['track'];
    }
    if (!empty($_GET['status'])) {
        $where[] = 'c.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['q'])) {
        $where[] = '(c.subject LIKE ? OR c.id LIKE ? OR c.reg_number LIKE ? OR c.agency LIKE ?)';
        $like = '%' . $_GET['q'] . '%';
        array_push($params, $like, $like, $like, $like);
    }

    /* ── exec drill-down filters ── */
    $active_st = "('received','screening','case','assigned','investigating','reporting')";
    $today_str = date('Y-m-d');
    $days_col  = "COALESCE(c.received_date, DATE(c.created_at))";

    if (!empty($_GET['drill'])) {
        switch ($_GET['drill']) {
            case 'due_today':
                $where[] = "c.status IN $active_st AND c.due_date = ?";
                $params[] = $today_str;
                break;
            case 'overdue':
                $where[] = "c.status IN $active_st AND c.due_date < ?";
                $params[] = $today_str;
                break;
            case 'not_started':
                $where[] = "c.status = 'received'";
                break;
            case 'pending30':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) > 30 AND DATEDIFF(?, $days_col) <= 60";
                $params[] = $today_str; $params[] = $today_str;
                break;
            case 'pending60':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) > 60 AND DATEDIFF(?, $days_col) <= 90";
                $params[] = $today_str; $params[] = $today_str;
                break;
            case 'pending90':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) > 90";
                $params[] = $today_str;
                break;
            case 'aging_0_15':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) BETWEEN 0 AND 15";
                $params[] = $today_str;
                break;
            case 'aging_16_30':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) BETWEEN 16 AND 30";
                $params[] = $today_str;
                break;
            case 'aging_31_60':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) BETWEEN 31 AND 60";
                $params[] = $today_str;
                break;
            case 'aging_61_90':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) BETWEEN 61 AND 90";
                $params[] = $today_str;
                break;
            case 'aging_90plus':
                $where[] = "c.status IN $active_st AND DATEDIFF(?, $days_col) > 90";
                $params[] = $today_str;
                break;
            case 'sla_r':
                // overdue: due_date < today
                $where[] = "c.status IN $active_st AND c.due_date IS NOT NULL AND c.due_date < ?";
                $params[] = $today_str;
                break;
            case 'sla_a':
                // amber: due_date >= today AND remaining <= GREATEST(2, CEIL(sla_days*0.25))
                $where[] = "c.status IN $active_st
                    AND c.due_date >= ?
                    AND DATEDIFF(c.due_date, ?) <= GREATEST(2, CEIL(COALESCE(ss.days, 30) * 0.25))";
                $params[] = $today_str; $params[] = $today_str;
                break;
            case 'sla_g':
                // green: due_date IS NULL OR remaining > GREATEST(2, CEIL(sla_days*0.25))
                $where[] = "c.status IN $active_st
                    AND (c.due_date IS NULL
                         OR DATEDIFF(c.due_date, ?) > GREATEST(2, CEIL(COALESCE(ss.days, 30) * 0.25)))";
                $params[] = $today_str;
                break;
        }
    }

    if (!empty($_GET['officer'])) {
        $where[] = 'c.assignee_id = ?';
        $params[] = $_GET['officer'];
    }
    if (!empty($_GET['cat'])) {
        $where[] = 'c.cat = ?';
        $params[] = $_GET['cat'];
    }
    if (!empty($_GET['agency'])) {
        $where[] = 'c.agency = ?';
        $params[] = $_GET['agency'];
    }

    $lawyerCol = in_array('lawyer_id', $caseColsList) ? 'c.lawyer_id AS lawyer' : 'NULL AS lawyer';
    $groupCol  = in_array('assigned_group', $caseColsList) ? 'c.assigned_group' : 'NULL AS assigned_group';
    $recvCol   = in_array('group_recv_no', $caseColsList) ? 'c.group_recv_no' : 'NULL AS group_recv_no';
    $sql = "SELECT c.id, c.reg_number AS reg, c.subject, c.track, c.cat, c.channel,
                   c.cls, c.status, c.priority, c.anon, c.complainant, c.contact,
                   c.agency, c.assignee_id AS assignee, $lawyerCol, $groupCol, $recvCol,
                   c.progress, c.received_date AS received, c.due_date AS due,
                   COALESCE(ss.days, 30) AS sla_days
            FROM cases c
            LEFT JOIN sla_settings ss ON ss.track = c.track AND ss.cat = c.cat"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY c.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $today = new DateTime(date('Y-m-d'));
    foreach ($rows as &$r) {
        $r['anon']     = (bool)$r['anon'];
        $r['progress'] = (int)$r['progress'];
        // คำนวณ SLA แบบ dynamic จาก due_date + sla_days
        $r['sla'] = calcSla($r['due'], (int)$r['sla_days'], $today, $r['status']);
        unset($r['sla_days']);
    }

    json_out($rows);
}

/* ====== POST /api/cases.php — สร้างเรื่องใหม่ ====== */
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $db   = getDB();

    $isStaff   = !empty($_SESSION['user_id']);
    $createdBy = $isStaff ? $_SESSION['user_id'] : null;

    // Validate required fields
    $subject = trim($body['subject'] ?? '');
    $track   = $body['track'] ?? '';
    $cat     = $body['cat'] ?? '';
    $validTracks = ['discipline','legal','general'];
    // public form ไม่ต้องระบุ track (หัวหน้าธุรการระบุเอง); staff ยังต้องระบุ
    if (!$subject) err('ข้อมูลไม่ครบถ้วน');
    if ($isStaff && !in_array($track, $validTracks, true)) err('ข้อมูลไม่ครบถ้วน');
    if (!$isStaff && $track !== '' && !in_array($track, $validTracks, true)) $track = '';
    $track = $track !== '' ? $track : null;
    $cat   = $cat   !== '' ? $cat   : null;

    $thYear = date('Y') + 543;
    $newId  = nextCaseId($db);
    $trackToken = generateTrackToken($db); // null ถ้ายังไม่ได้ migrate
    $channel = $isStaff ? ($body['channel'] ?? 'หนังสือราชการ') : ('เว็บไซต์ (' . (($body['identity'] ?? '') === 'anon' ? 'ไม่ประสงค์ออกนาม' : 'ยืนยันตัวตน') . ')');
    $anon    = (($body['identity'] ?? '') === 'anon') ? 1 : 0;
    $cls     = $body['cls'] ?? 'public';
    $today   = date('Y-m-d');

    if ($trackToken !== null) {
        $db->prepare(
            'INSERT INTO cases (id, track_token, subject, track, cat, channel, cls, status, priority, anon,
                                complainant, contact, agency, detail, created_by, received_date)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $newId, $trackToken, $subject, $track, $cat, $channel, $cls,
            'received',
            $body['priority'] ?? 'ปกติ',
            $anon,
            $anon ? 'ไม่ประสงค์ออกนาม' : ($body['name'] ?? null),
            $body['email'] ?? ($body['contact'] ?? null),
            $body['agency'] ?? null,
            $body['detail'] ?? null,
            $createdBy,
            $today,
        ]);
    } else {
        $db->prepare(
            'INSERT INTO cases (id, subject, track, cat, channel, cls, status, priority, anon,
                                complainant, contact, agency, detail, created_by, received_date)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $newId, $subject, $track, $cat, $channel, $cls,
            'received',
            $body['priority'] ?? 'ปกติ',
            $anon,
            $anon ? 'ไม่ประสงค์ออกนาม' : ($body['name'] ?? null),
            $body['email'] ?? ($body['contact'] ?? null),
            $body['agency'] ?? null,
            $body['detail'] ?? null,
            $createdBy,
            $today,
        ]);
    }

    // auto-event
    $db->prepare(
        'INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $newId,
        $isStaff ? 'นำเข้าเรื่องจากเอกสาร' : 'รับเรื่องผ่านเว็บไซต์',
        $isStaff ? 'เจ้าหน้าที่นิติการ' : 'ระบบอัตโนมัติ',
        date('j') . ' ' . ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')] . ' ' . $thYear . ' ' . date('H:i'),
        'ออกรหัสติดตาม ' . $newId,
        'done', 'inbox', 1,
    ]);

    // ย้ายไฟล์ชั่วคราวที่อัปโหลดพร้อมฟอร์ม
    $tmpFiles = $body['tmp_files'] ?? [];
    if ($tmpFiles && is_array($tmpFiles)) {
        $insFile = $db->prepare(
            'INSERT INTO case_files (case_id, filename, stored_name, size_label, cls) VALUES (?,?,?,?,?)'
        );
        $tmpDir  = __DIR__ . '/../uploads/tmp/';
        $destDir = __DIR__ . '/../uploads/';
        foreach ($tmpFiles as $tf) {
            $tmpName  = $tf['tmp']  ?? '';
            $origName = $tf['orig'] ?? '';
            $sizeLabel = $tf['size'] ?? '';
            // ตรวจความปลอดภัย: ชื่อต้องเป็น hex32.ext เท่านั้น
            if (!preg_match('/^[0-9a-f]{32}\.[a-z]{2,4}$/', $tmpName)) continue;
            $src = $tmpDir . $tmpName;
            if (!file_exists($src)) continue;
            rename($src, $destDir . $tmpName);
            $insFile->execute([$newId, $origName, $tmpName, $sizeLabel, 'public']);
        }
    }

    // เริ่ม SLA ขั้น "รับเรื่อง" อัตโนมัติ
    startSlaStep($db, $newId, 'receive');

    // ส่งอีเมลยืนยันให้ผู้ยื่น (ถ้า MAIL_ENABLED และมีอีเมล)
    $contactEmail = $body['email'] ?? ($body['contact'] ?? null);
    if ($contactEmail && !$isStaff) {
        if (!function_exists('sendMail')) @require_once __DIR__ . '/../config/mail.php';
        if (function_exists('sendMail')) {
            $displayToken = $trackToken ?: $newId;
            $trackUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                        . (dirname($_SERVER['SCRIPT_NAME'] ?? '', 2)) . '/?view=track&ticket=' . urlencode($displayToken);
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;background:#f5f4f2;margin:0;padding:32px 0">'
                  . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">'
                  . '<div style="background:#7a1e2e;padding:24px 28px"><h1 style="color:#fff;margin:0;font-size:20px">ระบบบริหารงานนิติการ · สอศ.</h1></div>'
                  . '<div style="padding:28px">'
                  . '<p style="margin:0 0 8px;font-size:15px">ระบบได้รับเรื่องของท่านเรียบร้อยแล้ว</p>'
                  . '<p style="margin:0 0 20px;color:#666;font-size:14px">กรุณาบันทึกรหัสติดตามด้านล่างไว้ เพื่อใช้ตรวจสอบสถานะเรื่องของท่าน</p>'
                  . '<div style="background:#f5f4f2;border-radius:8px;padding:18px 20px;margin-bottom:20px">'
                  . '<div style="font-size:12px;color:#888;margin-bottom:6px">รหัสติดตามเรื่อง (Ticket Code)</div>'
                  . '<div style="font-size:26px;font-weight:700;font-family:monospace;color:#7a1e2e;letter-spacing:.05em">' . htmlspecialchars($displayToken) . '</div>'
                  . '</div>'
                  . '<a href="' . htmlspecialchars($trackUrl) . '" style="display:inline-block;background:#7a1e2e;color:#fff;text-decoration:none;padding:11px 22px;border-radius:7px;font-size:14px;font-weight:600">ติดตามสถานะเรื่อง</a>'
                  . '<p style="margin:20px 0 0;font-size:12px;color:#aaa">อีเมลนี้ส่งจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ</p>'
                  . '</div></div></body></html>';
            @sendMail($contactEmail, '', 'รับเรื่องของท่านแล้ว — รหัสติดตาม ' . $displayToken, $html);
        }
    }

    audit('create_case', $newId);
    $out = ['id' => $newId];
    if ($trackToken !== null) $out['track_token'] = $trackToken;
    json_out($out, 201);
}

/* ====== PATCH /api/cases.php — อัปเดตสำนวน (staff only) ====== */
if ($method === 'PATCH') {
    $auth = require_auth();
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!$id) err('ต้องระบุ id');

    $allowed = ['status','assignee_id','progress','sla','reg_number','due_date','priority','cls'];
    // lawyer_id รองรับเฉพาะถ้ามีคอลัมน์ (graceful ก่อน migrate)
    $caseCols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('lawyer_id', $caseCols)) $allowed[] = 'lawyer_id';
    $set = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $body)) {
            $set[] = "$col = ?";
            if ($col === 'assignee_id')   $params[] = $body['assignee'] ?? null;
            elseif ($col === 'lawyer_id') $params[] = $body['lawyer_id'] ?: null;
            else                          $params[] = $body[$col];
        }
    }
    // handle assignee alias
    if (isset($body['assignee'])) {
        $set[]    = 'assignee_id = ?';
        $params[] = $body['assignee'] ?: null;
    }

    if (!$set) err('ไม่มีข้อมูลที่จะอัปเดต');

    $params[] = $id;
    $db->prepare('UPDATE cases SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);

    // บันทึก event + แจ้งเตือนถ้าเปลี่ยน assignee
    if (isset($body['assignee'])) {
        $newAssignee = $body['assignee'] ?: null;
        // หาชื่อนิติกร
        $stmt = $db->prepare('SELECT name FROM officers WHERE id = ?');
        $stmt->execute([$newAssignee]);
        $oname = $stmt->fetchColumn() ?: 'ยังไม่ระบุ';
        $maxOrd = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM case_events WHERE case_id = ?');
        $maxOrd->execute([$id]);
        $ord = $maxOrd->fetchColumn();
        $thYear = date('Y') + 543;
        $db->prepare(
            'INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$id, 'แต่งตั้งผู้สอบสวน', 'ระบบ',
            date('j') . ' ' . ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')] . ' ' . $thYear,
            'มอบหมาย ' . $oname, 'done', 'gavel', $ord]);

        // แจ้งเตือน in-system ให้นิติกรที่ได้รับมอบหมาย
        if ($newAssignee) {
            $uStmt = $db->prepare('SELECT id, display_name FROM users WHERE officer_id = ? AND active = 1 LIMIT 1');
            $uStmt->execute([$newAssignee]);
            $assignedUser = $uStmt->fetch();
            if ($assignedUser) {
                $cStmt = $db->prepare('SELECT subject FROM cases WHERE id = ?');
                $cStmt->execute([$id]);
                $subj = mb_substr($cStmt->fetchColumn() ?: $id, 0, 60);
                $db->prepare("
                    INSERT INTO notifications (user_id, case_id, notif_type, title, body)
                    VALUES (?,?,?,?,?)
                ")->execute([
                    (int)$assignedUser['id'], $id, 'assigned',
                    "📋 ได้รับมอบหมายสำนวนใหม่: {$subj}",
                    "สำนวน {$id} ถูกมอบหมายให้คุณดำเนินการ",
                ]);
            }
        }
    }

    // มอบหมายนิติกรผู้ดำเนินการ (clerk ส่งต่อให้นิติกรในกลุ่ม)
    if (array_key_exists('lawyer_id', $body) && in_array('lawyer_id', $caseCols)) {
        $newLawyer = $body['lawyer_id'] ?: null;
        if ($newLawyer) {
            $lname = $db->prepare('SELECT name FROM officers WHERE id = ?');
            $lname->execute([$newLawyer]);
            $lname = $lname->fetchColumn() ?: $newLawyer;

            $maxOrd = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM case_events WHERE case_id = ?');
            $maxOrd->execute([$id]);
            $ord = $maxOrd->fetchColumn();
            $thYear = date('Y') + 543;
            $db->prepare(
                'INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$id, 'มอบหมายนิติกรผู้ดำเนินการ', 'เจ้าหน้าที่ผู้รับผิดชอบ',
                date('j') . ' ' . ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')] . ' ' . $thYear,
                'ส่งเรื่องต่อให้ ' . $lname . ' ดำเนินการ', 'done', 'gavel', $ord]);

            // แจ้งเตือนนิติกรที่ได้รับมอบหมาย
            $uStmt = $db->prepare('SELECT id FROM users WHERE officer_id = ? AND active = 1 LIMIT 1');
            $uStmt->execute([$newLawyer]);
            $lawyerUser = $uStmt->fetch();
            if ($lawyerUser) {
                $cStmt = $db->prepare('SELECT subject FROM cases WHERE id = ?');
                $cStmt->execute([$id]);
                $subj = mb_substr($cStmt->fetchColumn() ?: $id, 0, 60);
                $db->prepare("INSERT INTO notifications (user_id, case_id, notif_type, title, body) VALUES (?,?,?,?,?)")
                   ->execute([(int)$lawyerUser['id'], $id, 'assigned',
                       "⚖️ ได้รับมอบหมายเป็นนิติกรผู้ดำเนินการ: {$subj}",
                       "สำนวน {$id} ถูกส่งต่อให้คุณดำเนินการ"]);
            }
        }
    }

    // sync SLA calendar event เมื่อเปลี่ยน assignee หรือ status
    if (isset($body['assignee']) || isset($body['assignee_id']) || isset($body['status'])) {
        try { syncSlaCalEvent($db, $id, (int)$auth['id']); } catch (Throwable) {}
    }

    audit('update_case', $id, json_encode($body, JSON_UNESCAPED_UNICODE));

    // ดึงข้อมูลล่าสุด
    $stmt = $db->prepare('SELECT c.*, o.name AS assignee_name FROM cases c LEFT JOIN officers o ON o.id = c.assignee_id WHERE c.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    json_out(buildCase($row, $db));
}

/* ====== DELETE /api/cases.php?id=xxx — ลบสำนวน (admin เท่านั้น) ====== */
if ($method === 'DELETE' && $id !== '') {
    $auth = require_auth();
    if ($auth['role'] !== 'admin') err('เฉพาะ admin เท่านั้นที่ลบสำนวนได้', 403);

    $db = getDB();

    // ตรวจว่าสำนวนมีอยู่จริง
    $stmt = $db->prepare('SELECT id, reg_number FROM cases WHERE id = ?');
    $stmt->execute([$id]);
    $case = $stmt->fetch();
    if (!$case) err('ไม่พบสำนวน', 404);

    // ยืนยันเลขรับสำนวน
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $confirm = trim($body['confirm_reg'] ?? '');
    $reg     = trim($case['reg_number'] ?? '');
    // fallback: ยอมรับ case ID เมื่อไม่มีเลขรับ (reg ว่างหรือ —)
    $expected = ($reg !== '' && $reg !== '—') ? $reg : $case['id'];
    if ($confirm === '' || $confirm !== $expected) {
        err('ข้อมูลยืนยันไม่ตรง ไม่สามารถลบได้', 422);
    }

    // ลบข้อมูลที่เกี่ยวข้องทั้งหมด (cascade)
    $db->prepare('DELETE FROM case_files  WHERE case_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM case_events WHERE case_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM cases       WHERE id = ?')->execute([$id]);

    audit('delete_case', $id, "ลบโดย {$auth['username']} ยืนยันด้วยเลขรับ {$confirm}");
    json_out(['ok' => true, 'deleted' => $id]);
}

err('Method not allowed', 405);
