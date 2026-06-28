<?php
require_once __DIR__ . '/_common.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = trim($_GET['id'] ?? '');

/* ====== helpers ====== */
function buildCase(array $row, PDO $db): array {
    // files
    $fs = $db->prepare('SELECT filename AS n, size_label AS s, cls AS c FROM case_files WHERE case_id = ?');
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
    $year = date('Y') + 543; // พ.ศ.
    $prefix = "CMP-{$year}-";
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
    require_auth();
    $db = getDB();

    $where  = [];
    $params = [];

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
    if (!$subject || !in_array($track, ['discipline','legal'], true)) {
        err('ข้อมูลไม่ครบถ้วน');
    }

    $thYear = date('Y') + 543;
    $newId  = nextCaseId($db);
    $channel = $isStaff ? ($body['channel'] ?? 'หนังสือราชการ') : ('เว็บไซต์ (' . (($body['identity'] ?? '') === 'anon' ? 'ไม่ประสงค์ออกนาม' : 'ยืนยันตัวตน') . ')');
    $anon    = (($body['identity'] ?? '') === 'anon') ? 1 : 0;
    $cls     = $body['cls'] ?? 'internal';
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

    // บันทึก event ถ้าเปลี่ยน assignee หรือ status
    if (isset($body['assignee'])) {
        // หาชื่อนิติกร
        $stmt = $db->prepare('SELECT name FROM officers WHERE id = ?');
        $stmt->execute([$body['assignee']]);
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
    }

    audit('update_case', $id, json_encode($body, JSON_UNESCAPED_UNICODE));

    // ดึงข้อมูลล่าสุด
    $stmt = $db->prepare('SELECT c.*, o.name AS assignee_name FROM cases c LEFT JOIN officers o ON o.id = c.assignee_id WHERE c.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    json_out(buildCase($row, $db));
}

err('Method not allowed', 405);
