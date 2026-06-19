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

    // events
    $ev = $db->prepare('SELECT title AS t, actor AS who, moment AS m, detail AS d, ev_status AS st, icon AS ic
                        FROM case_events WHERE case_id = ? ORDER BY sort_order');
    $ev->execute([$row['id']]);
    $row['events'] = $ev->fetchAll();

    // cast types
    $row['anon']     = (bool)$row['anon'];
    $row['progress'] = (int)$row['progress'];

    return $row;
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
    $isStaff = !empty($_SESSION['user_id']);
    if (!$isStaff) {
        // Public: คืนแค่ข้อมูลจำเป็นสำหรับติดตามสถานะ
        json_out([
            'id'      => $row['id'],
            'status'  => $row['status'],
            'channel' => $row['channel'],
            'received'=> $row['received_date'],
            'sla'     => $row['sla'],
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
                   c.agency, c.assignee_id AS assignee, c.sla,
                   c.progress, c.received_date AS received, c.due_date AS due
            FROM cases c"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY c.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['anon']     = (bool)$r['anon'];
        $r['progress'] = (int)$r['progress'];
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
