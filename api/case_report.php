<?php
/* ============================================================
   api/case_report.php — การดำเนินการของนิติกรผู้ดำเนินการ + สายรายงาน
   POST { action, case_id, note?, file_count? }
     action = 'return'     → นิติกรเกษียนกลับให้ ผอ.กลุ่ม ทบทวน (ปลดล็อกการเปลี่ยนนิติกร)
     action = 'report'     → นิติกรรายงานผลการดำเนินการ (ข้อความ/แนบไฟล์)
     action = 'forward_up' → ผอ.กลุ่ม เกษียนรายงานผลถึง ผอ.สำนัก ผ่านธุรการ
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$db     = getDB();

/* ── GET ?action=clerks — รายชื่อเจ้าหน้าที่ธุรการ (clerk) ทั้งระบบ ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'clerks') {
    $hasGM = (bool)$db->query("SHOW TABLES LIKE 'group_members'")->fetchColumn();
    // เป็น clerk ถ้าบทบาทตนเอง=clerk หรือ *กลุ่มใดกลุ่มหนึ่ง* ที่สังกัดมีบทบาท clerk
    $groupClerk = $hasGM
        ? "EXISTS(SELECT 1 FROM group_members gm JOIN group_roles gr ON gr.group_id = gm.group_id WHERE gm.user_id = u.id AND gr.role='clerk')"
        : "EXISTS(SELECT 1 FROM group_roles gr JOIN `groups` g ON g.id = gr.group_id WHERE g.name = u.group_name AND gr.role='clerk')";
    $rows = $db->query(
        "SELECT u.id, u.display_name, u.group_name, u.officer_id, u.role,
                ($groupClerk) AS is_group_clerk
         FROM users u WHERE u.active = 1 ORDER BY u.group_name, u.display_name"
    )->fetchAll();
    $clerks = array_values(array_filter($rows, fn($u) => $u['role'] === 'clerk' || (int)$u['is_group_clerk'] === 1));
    json_out($clerks);
}

/* ── GET ?action=recipients — รายชื่อปลายทางหนังสือส่ง (ผู้บริหารระดับสูง + หน่วยงานภายนอก) ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') !== 'recipients') err('Method not allowed', 405);
    $execs = $db->query("SELECT display_name FROM users
        WHERE role IN ('secretary','deputy_secretary') AND active=1
        ORDER BY role='secretary' DESC, display_name")->fetchAll(PDO::FETCH_COLUMN);
    try {
        $agencies = $db->query("SELECT name FROM lookup_items
            WHERE category='external_agency' AND active=1
            ORDER BY sort_order, name")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) { $agencies = []; }
    json_out(['executives' => $execs, 'agencies' => $agencies]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed', 405);

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$caseId = trim($body['case_id'] ?? '');
if ($caseId === '') err('ต้องระบุ case_id');

$cs = $db->prepare("SELECT * FROM cases WHERE id = ?");
$cs->execute([$caseId]);
$case = $cs->fetch();
if (!$case) err('ไม่พบสำนวน', 404);

$caseCols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);

// ชื่อผู้กระทำ
$un = $db->prepare("SELECT display_name FROM users WHERE id = ?");
$un->execute([$auth['id']]);
$actorName = $un->fetchColumn() ?: 'ผู้ใช้';

$thMonth = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][(int)date('n')];
$moment  = date('j') . ' ' . $thMonth . ' ' . (date('Y') + 543);

function addEvent(PDO $db, string $caseId, string $title, string $actor, string $moment, string $detail, string $icon): void {
    $maxOrd = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM case_events WHERE case_id = ?');
    $maxOrd->execute([$caseId]);
    $ord = $maxOrd->fetchColumn();
    $db->prepare("INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order, started_at)
                  VALUES (?,?,?,?,?, 'done', ?, ?, NOW())")
       ->execute([$caseId, $title, $actor, $moment, $detail, $icon, $ord]);
}

function notifyUser(PDO $db, $userId, string $caseId, string $type, string $title, string $body): void {
    if (!$userId) return;
    $db->prepare("INSERT INTO notifications (user_id, case_id, notif_type, title, body) VALUES (?,?,?,?,?)")
       ->execute([(int)$userId, $caseId, $type, $title, $body]);
}

// ผอ.กลุ่ม = หัวหน้ากลุ่ม (leader_id) ของกลุ่มที่ได้รับมอบหมาย
function groupLeaderUserId(PDO $db, ?string $groupName) {
    if (!$groupName) return null;
    try {
        $s = $db->prepare("SELECT leader_id FROM `groups` WHERE name = ? LIMIT 1");
        $s->execute([$groupName]);
        return $s->fetchColumn() ?: null;
    } catch (Throwable) { return null; }
}

$subjShort = mb_substr($case['subject'] ?? $caseId, 0, 50);

/* ── นิติกรเกษียนกลับให้ ผอ.กลุ่ม ทบทวน ── */
if ($action === 'return') {
    $note = trim($body['note'] ?? '');
    if (mb_strlen($note) < 3) err('กรุณาระบุข้อความเกษียนกลับอย่างน้อย 3 ตัวอักษร', 422);
    addEvent($db, $caseId, 'เกษียนกลับ ผอ.กลุ่ม', $actorName, $moment, $note, 'flag');
    // ปลดล็อกให้ ผอ.กลุ่ม เปลี่ยนนิติกร/แก้ข้อสั่งการได้อีก
    if (in_array('lawyer_sent_at', $caseCols)) {
        $db->prepare("UPDATE cases SET lawyer_sent_at = NULL WHERE id = ?")->execute([$caseId]);
    }
    notifyUser($db, groupLeaderUserId($db, $case['assigned_group'] ?? null), $caseId, 'return',
        "↩️ นิติกรเกษียนกลับเพื่อทบทวน: {$subjShort}", $note);
    audit('lawyer_return', $caseId, $note);
    json_out(['ok' => true]);
}

/* ── นิติกรบันทึก/แก้ไขร่างรายงานผล (ยังไม่ส่ง — แก้ได้จนกว่าจะส่งให้ ผอ.กลุ่ม) ── */
if ($action === 'report') {
    $hasReportCol = in_array('report_note', $caseCols);
    if ($hasReportCol && !empty($case['report_sent_at'])) {
        err('ส่งรายงานให้ ผอ.กลุ่ม แล้ว ไม่สามารถแก้ไขได้', 409);
    }
    $note      = trim($body['note'] ?? '');
    $fileCount = (int)($body['file_count'] ?? 0);
    if ($note === '' && $fileCount < 1) err('กรุณาพิมพ์รายงานผลหรือแนบไฟล์อย่างน้อย 1 อย่าง', 422);
    if ($hasReportCol) {
        $db->prepare("UPDATE cases SET report_note = ? WHERE id = ?")->execute([$note ?: null, $caseId]);
    }
    $db->prepare("UPDATE cases SET status='reporting' WHERE id = ? AND status NOT IN ('closed','rejected')")->execute([$caseId]);
    // เริ่ม SLA ขั้น "ตรวจข้อเท็จจริง / ดำเนินการตามข้อสั่งการ" อัตโนมัติเมื่อเริ่มบันทึกรายงานผล
    try { startSlaStep($db, $caseId, slaKeyForCase($db, $caseId, 'investigate')); } catch (Throwable) {}
    audit('lawyer_report_draft', $caseId, $note);
    json_out(['ok' => true]);
}

/* ── นิติกรส่งรายงานผลให้ ผอ.กลุ่ม (ล็อกการแก้ไข + แจ้งเตือน) ── */
if ($action === 'report_send') {
    $hasReportCol = in_array('report_note', $caseCols);
    if ($hasReportCol && !empty($case['report_sent_at'])) err('ส่งรายงานแล้ว', 409);
    $note = $hasReportCol ? trim($case['report_note'] ?? '') : '';
    $fc = $db->prepare("SELECT COUNT(*) FROM case_files WHERE case_id = ?");
    $fc->execute([$caseId]);
    $fileCount = (int)$fc->fetchColumn();
    if ($note === '' && $fileCount < 1) err('ยังไม่มีรายงานผลให้ส่ง กรุณาบันทึกรายงานก่อน', 422);
    $detail = $note !== '' ? $note : '(รายงานผลโดยแนบไฟล์)';
    if ($fileCount > 0) $detail .= "\n📎 มีไฟล์แนบในคลังสำนวน";
    addEvent($db, $caseId, 'รายงานผลการดำเนินการ', $actorName, $moment, $detail, 'checkCircle');
    if ($hasReportCol) $db->prepare("UPDATE cases SET report_sent_at = NOW() WHERE id = ?")->execute([$caseId]);
    notifyUser($db, groupLeaderUserId($db, $case['assigned_group'] ?? null), $caseId, 'report',
        "📄 นิติกรส่งรายงานผลการดำเนินการ: {$subjShort}", $detail);
    audit('lawyer_report_send', $caseId, $detail);
    json_out(['ok' => true]);
}

/* ── ผอ.กลุ่ม ส่งรายงานผลกลับให้ธุรการ (เสร็จสิ้นขั้นตรวจข้อเท็จจริง) ── */
if ($action === 'forward_up') {
    $note = trim($body['note'] ?? '');
    if (mb_strlen($note) < 3) err('กรุณาระบุข้อความอย่างน้อย 3 ตัวอักษร', 422);
    $track = caseWorkTrack($db, $case['assigned_group'] ?? null);
    if ($track === 'general') {
        // สายบริหารทั่วไป: หัวหน้าธุรการเสนอรายงานตรงถึง ผอ.สำนัก (ไม่ผ่านธุรการอีกชั้น)
        addEvent($db, $caseId, 'หัวหน้าธุรการเสนอรายงานถึง ผอ.สำนัก', $actorName, $moment, $note, 'flag');
        try { completeSlaStep($db, $caseId, 'ga_execute'); } catch (Throwable) {}
        try { startSlaStep($db, $caseId, 'ga_propose_boss'); } catch (Throwable) {}
        foreach ($db->query("SELECT id FROM users WHERE role='dir_admin' AND active=1")->fetchAll(PDO::FETCH_COLUMN) as $daId) {
            notifyUser($db, $daId, $caseId, 'clerk_forward', "📤 หัวหน้าธุรการเสนอรายงานผลถึงท่าน: {$subjShort}", $note);
        }
        audit('forward_report_up', $caseId, "[general] {$note}");
        json_out(['ok' => true]);
    }
    addEvent($db, $caseId, 'ผอ.กลุ่มส่งรายงานให้ธุรการ', $actorName, $moment, $note, 'flag');
    // เสร็จสิ้น SLA ขั้น "ตรวจข้อเท็จจริง"
    try { completeSlaStep($db, $caseId, 'investigate'); } catch (Throwable) {}
    // แจ้งธุรการเจ้าของเรื่อง (clerk = user ที่ผูกกับ assignee_id)
    if (!empty($case['assignee_id'])) {
        $cu = $db->prepare("SELECT id FROM users WHERE officer_id = ? AND active = 1 LIMIT 1");
        $cu->execute([$case['assignee_id']]);
        notifyUser($db, $cu->fetchColumn() ?: null, $caseId, 'forward_up',
            "📤 ผอ.กลุ่มส่งรายงานผลกลับให้ท่าน โปรดเกษียนต่อ ผอ.สำนัก: {$subjShort}", $note);
    }
    audit('forward_report_up', $caseId, $note);
    json_out(['ok' => true]);
}

/* ── ธุรการเกษียนรายงานผลต่อ ผอ.สำนัก (เริ่มขั้นเสนอผู้บังคับบัญชา) ── */
if ($action === 'clerk_forward') {
    $note = trim($body['note'] ?? '');
    if (mb_strlen($note) < 3) err('กรุณาระบุข้อความอย่างน้อย 3 ตัวอักษร', 422);
    addEvent($db, $caseId, 'ธุรการเกษียนรายงานถึง ผอ.สำนัก', $actorName, $moment, $note, 'flag');
    // เริ่ม SLA ขั้น "เสนอผู้บังคับบัญชา"
    try { startSlaStep($db, $caseId, 'propose_boss'); } catch (Throwable) {}
    foreach ($db->query("SELECT id FROM users WHERE role='dir_admin' AND active=1")->fetchAll(PDO::FETCH_COLUMN) as $daId) {
        notifyUser($db, $daId, $caseId, 'clerk_forward', "📤 ธุรการเกษียนรายงานผลถึงท่าน: {$subjShort}", $note);
    }
    audit('clerk_forward_up', $caseId, $note);
    json_out(['ok' => true]);
}

/* ── สายบริหารทั่วไป: เจ้าหน้าที่ธุรการรายงานการดำเนินงานตรงถึง ผอ.สำนัก ── */
if ($action === 'clerk_report') {
    $note      = trim($body['note'] ?? '');
    $fileCount = (int)($body['file_count'] ?? 0);
    if ($note === '' && $fileCount < 1) err('กรุณาพิมพ์รายงานหรือแนบไฟล์อย่างน้อย 1 อย่าง', 422);
    $detail = $note !== '' ? $note : '(รายงานการดำเนินงานโดยแนบไฟล์)';
    if ($fileCount > 0) $detail .= "\n📎 แนบไฟล์ในคลังสำนวน {$fileCount} ไฟล์";
    addEvent($db, $caseId, 'รายงานการดำเนินงาน', $actorName, $moment, $detail, 'checkCircle');
    if (in_array('report_note', $caseCols)) {
        $db->prepare("UPDATE cases SET report_note = ?, report_sent_at = NOW() WHERE id = ?")->execute([$note ?: null, $caseId]);
    }
    $db->prepare("UPDATE cases SET status='reporting' WHERE id = ? AND status NOT IN ('closed','rejected')")->execute([$caseId]);
    // เสร็จสิ้นขั้นดำเนินการ แล้วเริ่มขั้นเสนอผู้บังคับบัญชา (ให้ ผอ.สำนัก พิจารณาสั่งการ)
    try { completeSlaStep($db, $caseId, 'ga_assign'); } catch (Throwable) {}
    try { completeSlaStep($db, $caseId, 'ga_execute'); } catch (Throwable) {}
    try { startSlaStep($db, $caseId, 'ga_propose_boss'); } catch (Throwable) {}
    foreach ($db->query("SELECT id FROM users WHERE role='dir_admin' AND active=1")->fetchAll(PDO::FETCH_COLUMN) as $daId) {
        notifyUser($db, $daId, $caseId, 'clerk_report', "📄 เจ้าหน้าที่รายงานการดำเนินงาน: {$subjShort}", $detail);
    }
    audit('clerk_report', $caseId, $detail);
    json_out(['ok' => true]);
}

/* ── หัวหน้าธุรการ: เก็บเรื่อง — เสร็จสิ้นการดำเนินงาน ── */
if ($action === 'hs_archive') {
    $note = trim($body['note'] ?? '') ?: 'เก็บเรื่อง เสร็จสิ้นการดำเนินงาน';
    addEvent($db, $caseId, 'เก็บเรื่องเสร็จสิ้น', $actorName, $moment, $note, 'checkCircle');
    audit('hs_archive', $caseId, $note);
    json_out(['ok' => true]);
}

/* ── หัวหน้าธุรการ: ดำเนินการเป็นเรื่องใหม่ —
      เรื่องเดิมถูกจัดเก็บ + สำเนาเรื่อง/ไฟล์ทั้งหมดออกเป็นเลขรับใหม่ (status=received) ── */
if ($action === 'hs_reopen') {
    $note = trim($body['note'] ?? '') ?: 'นำกลับมาดำเนินการเป็นเรื่องใหม่';

    // ออกเลขเรื่องใหม่ (ตรรกะเดียวกับ nextCaseId ใน cases.php)
    $year = date('Y') + 543;
    try { $pfx = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_prefix'")->fetchColumn(); }
    catch (Throwable) { $pfx = null; }
    $pfx    = preg_replace('/[^A-Za-z0-9ก-๙]/', '', $pfx ?: 'CMP');
    $prefix = "{$pfx}-{$year}-";
    $last   = $db->prepare("SELECT id FROM cases WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
    $last->execute([$prefix . '%']);
    $lastId = $last->fetchColumn();
    $newId  = $prefix . str_pad($lastId ? ((int)substr($lastId, -4) + 1) : 1, 4, '0', STR_PAD_LEFT);

    // track_token ใหม่ (ถ้าคอลัมน์มี)
    $newToken = null;
    if (array_key_exists('track_token', $case)) {
        do {
            $newToken = strtoupper(bin2hex(random_bytes(5)));
            $ex = $db->prepare("SELECT id FROM cases WHERE track_token=?");
            $ex->execute([$newToken]);
        } while ($ex->fetchColumn());
    }

    // สำเนาข้อมูลเรื่อง (เริ่มกระบวนการใหม่ — ไม่คัดลอกผู้รับผิดชอบ/ข้อสั่งการ/รายงานเดิม)
    $copyCols = array_values(array_intersect(
        ['subject','track','cat','channel','cls','priority','anon','complainant','contact','agency','detail','sla'],
        $caseCols
    ));
    $insCols = array_merge(['id'], $copyCols, ['status','progress','received_date','created_by']);
    $insVals = [$newId];
    foreach ($copyCols as $cc) $insVals[] = $case[$cc];
    $insVals = array_merge($insVals, ['received', 0, date('Y-m-d'), (int)$auth['id']]);
    if ($newToken !== null) { $insCols[] = 'track_token'; $insVals[] = $newToken; }
    // เรื่องสืบเนื่อง — อ้างเลขเรื่องเดิม (ถ้ามีคอลัมน์)
    if (in_array('origin_case_id', $caseCols)) { $insCols[] = 'origin_case_id'; $insVals[] = $caseId; }
    $db->prepare("INSERT INTO cases (" . implode(',', $insCols) . ") VALUES (" .
        implode(',', array_fill(0, count($insVals), '?')) . ")")->execute($insVals);

    // สำเนาไฟล์ทั้งหมด (คัดลอกไฟล์จริงบนดิสก์ด้วย)
    $uploadDir = __DIR__ . '/../uploads/';
    $fq = $db->prepare("SELECT filename, stored_name, size_label, cls FROM case_files WHERE case_id = ?");
    $fq->execute([$caseId]);
    $nFiles = 0;
    foreach ($fq->fetchAll() as $f) {
        $newStored = $f['stored_name'];
        if ($f['stored_name'] && is_file($uploadDir . $f['stored_name'])) {
            $ext = pathinfo($f['stored_name'], PATHINFO_EXTENSION);
            $newStored = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
            if (!copy($uploadDir . $f['stored_name'], $uploadDir . $newStored)) $newStored = $f['stored_name'];
        }
        $db->prepare("INSERT INTO case_files (case_id, filename, stored_name, size_label, cls) VALUES (?,?,?,?,?)")
           ->execute([$newId, $f['filename'], $newStored, $f['size_label'], $f['cls']]);
        $nFiles++;
    }

    // เริ่มขั้น "รับเรื่อง" ของเรื่องใหม่ + บันทึกที่มา
    try { startSlaStep($db, $newId, 'receive'); } catch (Throwable) {}
    addEvent($db, $newId, 'รับเรื่องเข้าระบบ (เรื่องใหม่จาก ' . $caseId . ')', $actorName, $moment,
        $note . ($nFiles ? "\n📎 สำเนาไฟล์ {$nFiles} ไฟล์จากเรื่องเดิม" : ''), 'inbox');

    // เรื่องเดิม: บันทึกการจัดเก็บ (คงสถานะ closed)
    addEvent($db, $caseId, 'ดำเนินการเป็นเรื่องใหม่', $actorName, $moment,
        $note . "\nออกเลขเรื่องใหม่: {$newId}", 'flag');

    audit('hs_reopen', $caseId, "new={$newId} files={$nFiles}");
    json_out(['ok' => true, 'new_id' => $newId]);
}

/* ── หัวหน้าธุรการ: จัดทำหนังสือส่ง (เลขาธิการ สอศ. / หน่วยงานภายนอก) ── */
if ($action === 'hs_send_letter') {
    $recipient = trim($body['recipient'] ?? '');
    $note      = trim($body['note'] ?? '');
    if ($recipient === '') err('กรุณาระบุปลายทางหนังสือส่ง', 422);
    if (mb_strlen($note) < 10) err('กรุณาระบุรายละเอียดอย่างน้อย 10 ตัวอักษร', 422);
    $fileCount = (int)($body['file_count'] ?? 0);

    addEvent($db, $caseId, 'จัดทำหนังสือส่ง', $actorName, $moment,
        "ถึง: {$recipient}\n{$note}" . ($fileCount > 0 ? "\n📎 แนบไฟล์หลักฐาน {$fileCount} ไฟล์ (ดูในคลังสำนวน)" : ''), 'send');

    // ปลายทางเป็นผู้บริหารระดับสูงในระบบ → แจ้งเตือน; ไม่ใช่ → บันทึกเป็นหน่วยงานภายนอกไว้ใช้ครั้งต่อไป
    $eu = $db->prepare("SELECT id FROM users WHERE display_name = ? AND role IN ('secretary','deputy_secretary') AND active=1 LIMIT 1");
    $eu->execute([$recipient]);
    $execId = $eu->fetchColumn();
    if ($execId) {
        notifyUser($db, (int)$execId, $caseId, 'send_letter',
            "📨 หนังสือส่งถึงท่าน: {$subjShort}", $note);
    } else {
        try {
            $ex = $db->prepare("SELECT id FROM lookup_items WHERE category='external_agency' AND name=? LIMIT 1");
            $ex->execute([$recipient]);
            if (!$ex->fetchColumn()) {
                $db->prepare("INSERT INTO lookup_items (category, name, sort_order, active) VALUES ('external_agency', ?, 999, 1)")
                   ->execute([$recipient]);
            }
        } catch (Throwable) {}
    }

    audit('hs_send_letter', $caseId, "to={$recipient}");
    json_out(['ok' => true]);
}

err('action ไม่ถูกต้อง', 400);
