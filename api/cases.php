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

    // events ของสำนวนนี้
    $ev = $db->prepare('
        SELECT id, title AS t, actor AS who, moment AS m, detail AS d,
               ev_status AS st, icon AS ic, step_key, started_at, completed_at,
               attachment_name, attachment_path, attachment_size
        FROM case_events WHERE case_id = ? ORDER BY sort_order
    ');
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
    // ดึง prefix จาก app_settings (graceful fallback ถ้าตารางยังไม่มี)
    try {
        $pfx = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_prefix'")->fetchColumn();
    } catch (Throwable) { $pfx = null; }
    $pfx = preg_replace('/[^A-Za-z0-9ก-๙]/', '', $pfx ?: 'CMP');
    $prefix = "{$pfx}-{$year}-";
    $stmt = $db->prepare("SELECT id FROM cases WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int)substr($last, -4) + 1) : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/* ====== GET /api/cases.php?id=xxx — รายละเอียดสำนวน (สาธารณะ: เฉพาะสถานะ) ====== */
if ($method === 'GET' && $id !== '') {
    $db   = getDB();
    $stmt = $db->prepare('SELECT c.*, o.name AS assignee_name FROM cases c LEFT JOIN officers o ON o.id = c.assignee_id WHERE c.id = ?');
    $stmt->execute([$id]);
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
        json_out([
            'id'       => $row['id'],
            'status'   => $row['status'],
            'channel'  => $row['channel'],
            'received' => $row['received_date'],
            'sla'      => $row['sla'],
            'subject'  => $row['subject'],
            'email_ok' => $emailOk,
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

    $where  = [];
    $params = [];

    // กรองตาม role
    if ($auth['role'] === 'head_secretary') {
        // หัวหน้าธุรการ: เห็นเฉพาะสำนวนที่ยังไม่ได้มอบหมาย
        $where[] = 'c.assignee_id IS NULL';
    } elseif (in_array($auth['role'], ['officer', 'secretary', 'clerk'], true)) {
        // เจ้าหน้าที่/ธุรการทั่วไป: เห็นเฉพาะสำนวนที่มอบหมายให้ตัวเอง
        if ($myOfficerId) {
            $where[] = 'c.assignee_id = ?';
            $params[] = $myOfficerId;
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

    $sql = "SELECT c.id, c.reg_number AS reg, c.subject, c.track, c.cat, c.channel,
                   c.cls, c.status, c.priority, c.anon, c.complainant, c.contact,
                   c.agency, c.assignee_id AS assignee,
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
    if (!$subject || !in_array($track, ['discipline','legal','general'], true)) {
        err('ข้อมูลไม่ครบถ้วน');
    }

    $thYear = date('Y') + 543;
    $newId  = nextCaseId($db);
    $channel = $isStaff ? ($body['channel'] ?? 'หนังสือราชการ') : ('เว็บไซต์ (' . (($body['identity'] ?? '') === 'anon' ? 'ไม่ประสงค์ออกนาม' : 'ยืนยันตัวตน') . ')');
    $anon    = (($body['identity'] ?? '') === 'anon') ? 1 : 0;
    $cls     = $body['cls'] ?? 'public';
    $today   = date('Y-m-d');

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

    audit('create_case', $newId);
    json_out(['id' => $newId], 201);
}

/* ====== PATCH /api/cases.php — อัปเดตสำนวน (staff only) ====== */
if ($method === 'PATCH') {
    $auth = require_auth();
    $db   = getDB();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!$id) err('ต้องระบุ id');

    $allowed = ['status','assignee_id','progress','sla','reg_number','due_date','priority','cls'];
    $set = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $body)) {
            $key = $col === 'assignee_id' ? 'assignee' : $col;
            $set[]    = "$col = ?";
            $params[] = $col === 'assignee_id' ? ($body['assignee'] ?? null) : $body[$col];
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
