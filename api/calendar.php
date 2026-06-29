<?php
require_once __DIR__ . '/_common.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);
$auth   = require_auth();
$db     = getDB();

// ดึงข้อมูล group_name และ officer_id ของผู้ใช้ปัจจุบัน
$_uRow = $db->prepare("SELECT officer_id, group_name FROM users WHERE id=?");
$_uRow->execute([$auth['id']]);
$_uData = $_uRow->fetch() ?: [];
$currentOfficerId = $_uData['officer_id'] ?? null;
$currentGroupName = $_uData['group_name'] ?? null;
$currentRole      = $auth['role'];

/* ---------- helper: trigger notifications ---------- */
function triggerCalendarNotifs(PDO $db): void {
    $today = date('Y-m-d');

    // ดึง events ที่ยังไม่ส่ง notif
    $rows = $db->query("
        SELECT e.*, o.name AS officer_name,
               u.id AS officer_user_id
        FROM calendar_events e
        LEFT JOIN officers o ON o.id = e.officer_id
        LEFT JOIN users    u ON u.officer_id = e.officer_id AND u.active = 1
        WHERE e.event_date >= CURDATE()
          AND (e.notif_3_sent = 0 OR e.notif_7_sent = 0)
    ")->fetchAll();

    // supervisors (dir_legal, dir_admin)
    $supIds = $db->query("SELECT id FROM users WHERE role IN ('dir_legal','dir_admin') AND active=1")
                 ->fetchAll(PDO::FETCH_COLUMN);

    $notifIns = $db->prepare("INSERT IGNORE INTO notifications (user_id,case_id,notif_type,title,body) VALUES (?,?,?,?,?)");

    foreach ($rows as $r) {
        $daysLeft = (int)((strtotime($r['event_date']) - strtotime($today)) / 86400);
        $label    = $r['title'];
        $dateStr  = date('d/m/') . (date('Y') + 543);

        // 7 วันก่อน
        if ($daysLeft <= 7 && !$r['notif_7_sent']) {
            $targets = array_filter(array_unique(array_merge([$r['officer_user_id']], $supIds)));
            foreach ($targets as $uid) {
                if ($uid) $notifIns->execute([$uid, $r['case_id'], 'calendar', "กิจกรรมอีก 7 วัน", "{$label} — {$r['event_date']}"]);
            }
            $db->prepare("UPDATE calendar_events SET notif_7_sent=1 WHERE id=?")->execute([$r['id']]);
        }
        // 3 วันก่อน
        if ($daysLeft <= 3 && !$r['notif_3_sent']) {
            $targets = array_filter(array_unique(array_merge([$r['officer_user_id']], $supIds)));
            foreach ($targets as $uid) {
                if ($uid) $notifIns->execute([$uid, $r['case_id'], 'calendar', "กิจกรรมอีก 3 วัน", "{$label} — {$r['event_date']}"]);
            }
            $db->prepare("UPDATE calendar_events SET notif_3_sent=1 WHERE id=?")->execute([$r['id']]);
        }
    }

    // เกินกำหนด 1/3/7 วัน
    $over = $db->query("
        SELECT e.*, u.id AS officer_user_id
        FROM calendar_events e
        LEFT JOIN users u ON u.officer_id = e.officer_id AND u.active=1
        WHERE e.event_date < CURDATE() AND e.notif_over_sent = 0
    ")->fetchAll();
    foreach ($over as $r) {
        $daysOver = (int)((strtotime($today) - strtotime($r['event_date'])) / 86400);
        if (in_array($daysOver, [1,3,7])) {
            $targets = array_filter(array_unique(array_merge([$r['officer_user_id']], $supIds)));
            foreach ($targets as $uid) {
                if ($uid) $notifIns->execute([$uid, $r['case_id'], 'calendar_overdue',
                    "กิจกรรมเกินกำหนด {$daysOver} วัน", "{$r['title']} — ครบกำหนด {$r['event_date']}"]);
            }
        }
        if ($daysOver >= 7) {
            $db->prepare("UPDATE calendar_events SET notif_over_sent=1 WHERE id=?")->execute([$r['id']]);
        }
    }
}

/* ====== GET ?year=&month= — events + case due dates ====== */
if ($method === 'GET' && !$id) {
    triggerCalendarNotifs($db);

    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('m'));
    $from  = sprintf('%04d-%02d-01', $year, $month);
    $to    = date('Y-m-t', strtotime($from));

    // ── กำหนด filter officer_id ตาม role ──
    $filterOfficerId = $_GET['officer_id'] ?? '';   // '' = ทั้งหมด
    $filterGroupName = null;

    if ($currentRole === 'officer') {
        // เจ้าหน้าที่เห็นแค่ปฏิทินตัวเอง
        $filterOfficerId = $currentOfficerId;
    } elseif ($currentRole === 'dir_legal') {
        // หัวหน้ากลุ่มเห็นแค่ officer ใน group เดียวกัน
        $filterGroupName = $currentGroupName;
        // ถ้าเลือก officer เฉพาะคน ตรวจว่าอยู่ใน group เดียวกัน
        if ($filterOfficerId && $filterOfficerId !== '') {
            $chk = $db->prepare("SELECT id FROM officers WHERE id=? AND group_name=?");
            $chk->execute([$filterOfficerId, $currentGroupName]);
            if (!$chk->fetch()) $filterOfficerId = '';
        }
    }
    // dir_admin, deputy_secretary, secretary, admin → ดูทั้งหมด ไม่บังคับ filter

    // สร้าง WHERE clause สำหรับ officer filter
    $evOfficerWhere = '';
    $evParams = [$from, $to];
    if ($filterOfficerId) {
        $evOfficerWhere = ' AND e.officer_id = ?';
        $evParams[] = $filterOfficerId;
    } elseif ($filterGroupName) {
        $evOfficerWhere = ' AND (o.group_name = ? OR e.officer_id IS NULL)';
        $evParams[] = $filterGroupName;
    }

    // calendar events
    $evStmt = $db->prepare("
        SELECT e.*, o.name AS officer_name, o.init AS officer_init,
               u.display_name AS created_by_name
        FROM calendar_events e
        LEFT JOIN officers o ON o.id  = e.officer_id
        LEFT JOIN users    u ON u.id  = e.created_by
        WHERE e.event_date BETWEEN ? AND ?
        {$evOfficerWhere}
        ORDER BY e.event_date, e.start_time
    ");
    $evStmt->execute($evParams);
    $events = $evStmt->fetchAll();

    // helper สร้าง WHERE และ params สำหรับ c.assignee_id / o.group_name
    $caseOfficerWhere = '';
    $caseParams2 = [$from, $to];
    $caseParams1 = [$from, $to];
    if ($filterOfficerId) {
        $caseOfficerWhere = ' AND c.assignee_id = ?';
        $caseParams2[] = $filterOfficerId;
        $caseParams1[] = $filterOfficerId;
    } elseif ($filterGroupName) {
        $caseOfficerWhere = ' AND o.group_name = ?';
        $caseParams2[] = $filterGroupName;
        $caseParams1[] = $filterGroupName;
    }

    // case due dates ที่ยังไม่ปิด
    $duStmt = $db->prepare("
        SELECT c.id AS case_id, c.subject, c.due_date, c.status,
               o.name AS officer_name, o.init AS officer_init
        FROM cases c
        LEFT JOIN officers o ON o.id = c.assignee_id
        WHERE c.due_date BETWEEN ? AND ? AND c.status != 'closed'
        {$caseOfficerWhere}
        ORDER BY c.due_date
    ");
    $duStmt->execute($caseParams2);
    $dueDates = $duStmt->fetchAll();

    // สำนวนที่ปิดในเดือนนี้
    $clStmt = $db->prepare("
        SELECT c.id AS case_id, c.subject, DATE(c.updated_at) AS event_date,
               o.name AS officer_name, o.init AS officer_init
        FROM cases c
        LEFT JOIN officers o ON o.id = c.assignee_id
        WHERE c.status = 'closed' AND DATE(c.updated_at) BETWEEN ? AND ?
        {$caseOfficerWhere}
        ORDER BY c.updated_at
    ");
    $clStmt->execute($caseParams2);
    $closedCases = $clStmt->fetchAll();

    // งานย่อยที่เสร็จในเดือนนี้ (กรองด้วย ct.officer_id หรือ o.group_name)
    $taskWhere = '';
    $taskParams = [$from, $to];
    if ($filterOfficerId) {
        $taskWhere = ' AND ct.officer_id = ?';
        $taskParams[] = $filterOfficerId;
    } elseif ($filterGroupName) {
        $taskWhere = ' AND o.group_name = ?';
        $taskParams[] = $filterGroupName;
    }
    $tdStmt = $db->prepare("
        SELECT ct.id, ct.task_name, ct.case_id, DATE(ct.completed_at) AS event_date,
               c.subject AS case_subject, o.name AS officer_name, o.init AS officer_init
        FROM case_tasks ct
        JOIN cases c ON c.id = ct.case_id
        LEFT JOIN officers o ON o.id = ct.officer_id
        WHERE ct.status = 'done' AND ct.completed_at IS NOT NULL
          AND DATE(ct.completed_at) BETWEEN ? AND ?
        {$taskWhere}
        ORDER BY ct.completed_at
    ");
    $tdStmt->execute($taskParams);
    $doneTasks = $tdStmt->fetchAll();

    // รายชื่อ officers ที่ผู้ใช้นี้มีสิทธิ์ดู (สำหรับ dropdown ใน frontend)
    if ($currentRole === 'officer') {
        $visOfficers = [];
    } elseif ($currentRole === 'dir_legal') {
        $voStmt = $db->prepare("SELECT id, name, init FROM officers WHERE group_name=? AND active=1 ORDER BY name");
        $voStmt->execute([$currentGroupName]);
        $visOfficers = $voStmt->fetchAll();
    } else {
        $visOfficers = $db->query("SELECT id, name, init FROM officers WHERE active=1 ORDER BY name")->fetchAll();
    }

    // SLA deadlines — สำนวนที่ยังไม่ปิดและมีวันครบ SLA ในเดือนนี้
    $slaWhere = '';
    $slaParams = [$from, $to];
    if ($filterOfficerId) {
        $slaWhere = ' AND c.assignee_id = ?';
        $slaParams[] = $filterOfficerId;
    } elseif ($filterGroupName) {
        $slaWhere = ' AND o.group_name = ?';
        $slaParams[] = $filterGroupName;
    }
    $slaStmt = $db->prepare("
        SELECT c.id AS case_id, c.subject, c.track, c.cat, c.received_date,
               c.sla AS sla_color, c.status,
               DATE_ADD(c.received_date, INTERVAL ss.days DAY) AS sla_deadline,
               ss.days AS sla_days,
               o.name AS officer_name, o.init AS officer_init
        FROM cases c
        LEFT JOIN officers o ON o.id = c.assignee_id
        JOIN sla_settings ss ON ss.track = c.track AND ss.cat = c.cat
        WHERE c.status != 'closed'
          AND c.received_date IS NOT NULL
          AND DATE_ADD(c.received_date, INTERVAL ss.days DAY) BETWEEN ? AND ?
        {$slaWhere}
        ORDER BY sla_deadline
    ");
    $slaStmt->execute($slaParams);
    $slaDeadlines = $slaStmt->fetchAll();

    json_out(['events' => $events, 'due_dates' => $dueDates,
              'closed_cases' => $closedCases, 'done_tasks' => $doneTasks,
              'sla_deadlines' => $slaDeadlines,
              'visible_officers' => $visOfficers,
              'filter_officer_id' => $filterOfficerId]);
}

/* ====== POST — สร้าง event ====== */
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['event_type','title','event_date'];
    foreach ($required as $k) { if (empty($body[$k])) err("ต้องระบุ {$k}", 400); }

    $validTypes = ['meeting','court','investigation','document','committee'];
    if (!in_array($body['event_type'], $validTypes)) err('event_type ไม่ถูกต้อง', 400);

    $db->prepare("
        INSERT INTO calendar_events (event_type,title,event_date,start_time,end_time,case_id,officer_id,created_by,note)
        VALUES (?,?,?,?,?,?,?,?,?)
    ")->execute([
        $body['event_type'],
        trim($body['title']),
        $body['event_date'],
        $body['start_time'] ?: null,
        $body['end_time']   ?: null,
        $body['case_id']    ?: null,
        $body['officer_id'] ?: null,
        $auth['id'],
        $body['note']       ?: null,
    ]);
    $newId = $db->lastInsertId();

    $stmt = $db->prepare("SELECT e.*, o.name AS officer_name, o.init AS officer_init FROM calendar_events e LEFT JOIN officers o ON o.id=e.officer_id WHERE e.id=?");
    $stmt->execute([$newId]);
    audit('cal_create', (string)$newId, $body['title']);
    json_out($stmt->fetch());
}

/* ====== PATCH ?id= — แก้ไข event ====== */
if ($method === 'PATCH' && $id) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $ev = $db->prepare("SELECT * FROM calendar_events WHERE id=?");
    $ev->execute([$id]); $e = $ev->fetch();
    if (!$e) err('ไม่พบกิจกรรม', 404);

    $fields = ['event_type','title','event_date','start_time','end_time','case_id','officer_id','note'];
    $sets = []; $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $body)) {
            $sets[] = "{$f}=?";
            $params[] = ($body[$f] === '' ? null : $body[$f]);
        }
    }
    if ($sets) {
        $params[] = $id;
        $db->prepare("UPDATE calendar_events SET ".implode(',', $sets)." WHERE id=?")->execute($params);
    }

    $ev->execute([$id]);
    audit('cal_update', (string)$id, $body['title'] ?? $e['title']);
    json_out($ev->fetch());
}

/* ====== DELETE ?id= — ลบ event ====== */
if ($method === 'DELETE' && $id) {
    $ev = $db->prepare("SELECT id,title FROM calendar_events WHERE id=?");
    $ev->execute([$id]); $e = $ev->fetch();
    if (!$e) err('ไม่พบกิจกรรม', 404);

    $db->prepare("DELETE FROM calendar_events WHERE id=?")->execute([$id]);
    audit('cal_delete', (string)$id, $e['title']);
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
