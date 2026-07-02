<?php
/* ============================================================
   api/admin_testdata.php — จัดการข้อมูลทดสอบระบบ (admin เท่านั้น)
   GET  ?action=seq_info                     — ข้อมูลเลขทะเบียนหลัก + เลขรับของทุกกลุ่ม
   POST { action:'bulk_delete',    ids[], password }        — ลบหลายเรื่อง + ทุกอย่างที่เกี่ยวข้อง
   POST { action:'reset_main_seq', next_seq, password }     — ตั้งเลขทะเบียนหลักเรื่องถัดไป
   POST { action:'reset_group_seq', group_id, next_seq, password } — ตั้งเลขรับกลุ่มถัดไป
   ทุก action ที่เขียนข้อมูลต้องยืนยันรหัสผ่าน admin
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth = require_auth();
if ($auth['role'] !== 'admin') err('เฉพาะ admin เท่านั้น', 403);
$db = getDB();

function verifyAdminPassword(PDO $db, int $userId, string $password): void {
    if ($password === '') err('กรุณายืนยันรหัสผ่าน admin', 422);
    $s = $db->prepare("SELECT password_hash FROM users WHERE id = ? AND active = 1");
    $s->execute([$userId]);
    $hash = $s->fetchColumn();
    if (!$hash || !password_verify($password, $hash)) err('รหัสผ่านไม่ถูกต้อง', 403);
}

function mainSeqInfo(PDO $db): array {
    $year = date('Y') + 543;
    try { $pfx = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_prefix'")->fetchColumn(); }
    catch (Throwable) { $pfx = null; }
    $pfx    = preg_replace('/[^A-Za-z0-9ก-๙]/', '', $pfx ?: 'CMP');
    $prefix = "{$pfx}-{$year}-";
    $last = $db->prepare("SELECT id FROM cases WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
    $last->execute([$prefix . '%']);
    $lastId = $last->fetchColumn();
    $maxSeq = $lastId ? (int)substr($lastId, -4) : 0;
    try { $override = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_next_seq'")->fetchColumn(); }
    catch (Throwable) { $override = null; }
    return ['prefix' => $prefix, 'max_seq' => $maxSeq, 'next_override' => $override !== false && $override !== null ? (int)$override : null];
}

/* ── GET ?action=seq_info ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') !== 'seq_info') err('Method not allowed', 405);
    $year = date('Y') + 543;
    $groups = [];
    try {
        foreach ($db->query("SELECT id, name, recv_prefix FROM `groups` ORDER BY name")->fetchAll() as $g) {
            $m = $db->prepare("SELECT COALESCE(MAX(seq),0) FROM group_receipts WHERE group_id = ? AND year = ?");
            $m->execute([(int)$g['id'], $year]);
            $maxSeq = (int)$m->fetchColumn();
            $ov = $db->prepare("SELECT `value` FROM app_settings WHERE `key` = ?");
            $ov->execute(["group_recv_next_seq_{$g['id']}_{$year}"]);
            $override = $ov->fetchColumn();
            $groups[] = [
                'id' => (int)$g['id'], 'name' => $g['name'],
                'recv_prefix' => $g['recv_prefix'],
                'max_seq' => $maxSeq,
                'next_override' => ($override !== false && $override !== null) ? (int)$override : null,
            ];
        }
    } catch (Throwable) {}
    json_out(['year' => $year, 'main' => mainSeqInfo($db), 'groups' => $groups]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed', 405);
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
verifyAdminPassword($db, (int)$auth['id'], (string)($body['password'] ?? ''));

/* ── ลบหลายเรื่อง + ทุกอย่างที่เกี่ยวข้อง ─────────────────── */
if ($action === 'bulk_delete') {
    $ids = array_values(array_filter(array_map('trim', (array)($body['ids'] ?? []))));
    if (!$ids) err('กรุณาเลือกเรื่องที่ต้องการลบ', 422);
    if (count($ids) > 200) err('ลบได้ครั้งละไม่เกิน 200 เรื่อง', 422);

    $uploadDir = __DIR__ . '/../uploads/';
    $deleted = []; $notFound = [];

    foreach ($ids as $cid) {
        $ck = $db->prepare("SELECT id FROM cases WHERE id = ?");
        $ck->execute([$cid]);
        if (!$ck->fetchColumn()) { $notFound[] = $cid; continue; }

        $db->beginTransaction();
        try {
            // ลบไฟล์จริงบนดิสก์ (คลังสำนวน + ไฟล์แนบ event)
            $fs = $db->prepare("SELECT stored_name FROM case_files WHERE case_id = ?");
            $fs->execute([$cid]);
            foreach ($fs->fetchAll(PDO::FETCH_COLUMN) as $sn) {
                if ($sn && is_file($uploadDir . $sn)) @unlink($uploadDir . $sn);
            }
            $ea = $db->prepare("SELECT attachment_path FROM case_events WHERE case_id = ? AND attachment_path IS NOT NULL");
            $ea->execute([$cid]);
            foreach ($ea->fetchAll(PDO::FETCH_COLUMN) as $ap) {
                if ($ap && is_file($uploadDir . $ap)) @unlink($uploadDir . $ap);
            }

            // ลบแถวในทุกตารางที่เกี่ยวข้อง (ตารางที่อาจไม่มี → ข้าม)
            foreach (['case_files','case_events','case_task_proposals','notifications',
                      'notification_log','group_receipts','case_tasks','calendar_events'] as $tbl) {
                try { $db->prepare("DELETE FROM `$tbl` WHERE case_id = ?")->execute([$cid]); }
                catch (Throwable) {}
            }
            $db->prepare("DELETE FROM cases WHERE id = ?")->execute([$cid]);
            $db->commit();
            $deleted[] = $cid;
            audit('testdata_delete_case', $cid, 'ลบข้อมูลทดสอบ (bulk)');
        } catch (Throwable $e) {
            $db->rollBack();
            err("ลบ {$cid} ล้มเหลว: " . $e->getMessage(), 500);
        }
    }
    json_out(['ok' => true, 'deleted' => $deleted, 'not_found' => $notFound]);
}

/* ── ตั้งเลขทะเบียนหลักของเรื่องถัดไป ─────────────────────── */
if ($action === 'reset_main_seq') {
    $next = (int)($body['next_seq'] ?? 0);
    if ($next < 1 || $next > 9999) err('เลขต้องอยู่ระหว่าง 1–9999', 422);
    $info = mainSeqInfo($db);
    if ($info['max_seq'] >= $next) {
        err("มีเรื่องเลข {$info['prefix']}" . str_pad($info['max_seq'], 4, '0', STR_PAD_LEFT) .
            " อยู่ในระบบแล้ว — เลขถัดไปต้องมากกว่า {$info['max_seq']}", 422);
    }
    $db->prepare("INSERT INTO app_settings (`key`,`value`) VALUES ('case_id_next_seq', ?)
                  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([(string)$next]);
    audit('testdata_reset_main_seq', 'app_settings', "next={$next}");
    json_out(['ok' => true, 'next_id' => $info['prefix'] . str_pad($next, 4, '0', STR_PAD_LEFT)]);
}

/* ── ตั้งเลขรับหนังสือถัดไปของกลุ่ม ───────────────────────── */
if ($action === 'reset_group_seq') {
    $gid  = (int)($body['group_id'] ?? 0);
    $next = (int)($body['next_seq'] ?? 0);
    if (!$gid) err('ต้องระบุ group_id', 422);
    if ($next < 1 || $next > 99999) err('เลขต้องอยู่ระหว่าง 1–99999', 422);
    $g = $db->prepare("SELECT id, name FROM `groups` WHERE id = ?");
    $g->execute([$gid]);
    $grp = $g->fetch();
    if (!$grp) err('ไม่พบกลุ่ม', 404);

    $year = date('Y') + 543;
    $m = $db->prepare("SELECT COALESCE(MAX(seq),0) FROM group_receipts WHERE group_id = ? AND year = ?");
    $m->execute([$gid, $year]);
    $maxSeq = (int)$m->fetchColumn();
    if ($maxSeq >= $next) {
        err("กลุ่ม {$grp['name']} มีเลขรับถึงลำดับ {$maxSeq} แล้ว — เลขถัดไปต้องมากกว่า {$maxSeq}", 422);
    }
    $db->prepare("INSERT INTO app_settings (`key`,`value`) VALUES (?, ?)
                  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
       ->execute(["group_recv_next_seq_{$gid}_{$year}", (string)$next]);
    audit('testdata_reset_group_seq', (string)$gid, "next={$next} year={$year}");
    json_out(['ok' => true]);
}

err('action ไม่ถูกต้อง', 400);
