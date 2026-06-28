<?php
/**
 * api/cron.php — ตัวส่งการแจ้งเตือนอัตโนมัติ SLA
 *
 * เรียกใช้ทุกวัน (เช่น 08:00 น.) ผ่าน:
 *   - Windows Task Scheduler: php C:\xampp\htdocs\vec.cgts\api\cron.php
 *   - HTTP (ต้องใส่ token): GET /api/cron.php?token=<CRON_TOKEN>
 *   - UNIX cron: 0 8 * * * php /path/to/api/cron.php
 */
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

/* ── ป้องกันการเรียกโดยไม่ได้รับอนุญาต ── */
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $tok = $_GET['token'] ?? '';
    if ($tok !== CRON_TOKEN) {
        http_response_code(403);
        die(json_encode(['error' => 'Unauthorized']));
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$db    = getDB();
$today = new DateTime(date('Y-m-d'));
$log   = [];

function logLine(string $msg): void {
    global $log;
    $log[] = $msg;
    if (PHP_SAPI === 'cli') echo $msg . "\n";
}

/**
 * ตรวจว่าเคยส่งแจ้งเตือนประเภทนี้ให้ user นี้สำหรับ case นี้แล้วหรือยัง
 * สำหรับ over_weekly จะตรวจว่าส่งไปนานกว่า 6 วันแล้วหรือยัง
 */
function alreadySent(PDO $db, string $caseId, string $type, int $userId, string $channel = 'system'): bool {
    if ($type === 'over_weekly') {
        $s = $db->prepare("
            SELECT sent_at FROM notification_log
            WHERE case_id=? AND notif_type=? AND user_id=? AND channel=?
            ORDER BY sent_at DESC LIMIT 1
        ");
        $s->execute([$caseId, $type, $userId, $channel]);
        $row = $s->fetch();
        if (!$row) return false;
        $sentAt = new DateTime($row['sent_at']);
        $now    = new DateTime();
        return $now->diff($sentAt)->days < 7;
    }
    $s = $db->prepare("
        SELECT 1 FROM notification_log
        WHERE case_id=? AND notif_type=? AND user_id=? AND channel=? LIMIT 1
    ");
    $s->execute([$caseId, $type, $userId, $channel]);
    return (bool)$s->fetchColumn();
}

/**
 * บันทึกว่าส่งแล้ว
 */
function markSent(PDO $db, string $caseId, string $type, int $userId, string $channel = 'system'): void {
    $db->prepare("
        INSERT INTO notification_log (case_id, notif_type, user_id, channel) VALUES (?,?,?,?)
    ")->execute([$caseId, $type, $userId, $channel]);
}

/**
 * สร้าง notification ในระบบ + ส่งอีเมล (ถ้ามี)
 */
function notify(
    PDO $db, int $userId, string $caseId, string $type,
    string $title, string $body, ?string $email, ?string $emailName
): void {
    // in-system
    if (!alreadySent($db, $caseId, $type, $userId, 'system')) {
        $db->prepare("
            INSERT INTO notifications (user_id, case_id, notif_type, title, body)
            VALUES (?,?,?,?,?)
        ")->execute([$userId, $caseId, $type, $title, $body]);
        markSent($db, $caseId, $type, $userId, 'system');
        logLine("  [system] → user#{$userId} {$type}: {$title}");
    }

    // email
    if ($email && !alreadySent($db, $caseId, $type, $userId, 'email')) {
        $html = emailTemplate($title, $body, $caseId);
        if (sendMail($email, $emailName ?? '', $title, $html)) {
            markSent($db, $caseId, $type, $userId, 'email');
            logLine("  [email] → {$email} {$type}");
        }
    }
}

function emailTemplate(string $title, string $body, string $caseId): string {
    return <<<HTML
<!doctype html><html><head><meta charset="UTF-8"></head>
<body style="font-family:sans-serif;color:#222;background:#f8f8f8;padding:24px">
  <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:10px;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,.1)">
    <div style="background:#6b1d2a;color:#fff;border-radius:8px;padding:16px 20px;margin-bottom:20px">
      <b>ระบบรับเรื่องร้องเรียน–ร้องทุกข์ สอศ.</b>
    </div>
    <h2 style="margin:0 0 12px;font-size:17px;color:#6b1d2a">{$title}</h2>
    <p style="margin:0 0 16px;line-height:1.7;color:#444">{$body}</p>
    <p style="margin:0;font-size:12px;color:#999">รหัสสำนวน: <b>{$caseId}</b></p>
  </div>
</body></html>
HTML;
}

/* ════════════════════════════════════════════════════════
   ดึงเรื่องที่ยัง active ทั้งหมด
════════════════════════════════════════════════════════ */
$active_statuses = "('received','screening','case','assigned','investigating','reporting')";

$cases = $db->query("
    SELECT c.id, c.subject, c.due_date, c.assignee_id, c.track, c.cat,
           COALESCE(ss.days, 30) AS sla_days
    FROM cases c
    LEFT JOIN sla_settings ss ON ss.track = c.track AND ss.cat = c.cat
    WHERE c.status IN {$active_statuses}
")->fetchAll();

logLine(sprintf('[%s] cron เริ่มทำงาน — %d สำนวน active', date('Y-m-d H:i:s'), count($cases)));

/* ─── บทบาทสำหรับ escalation ─── */
$escalateRoles = [
    'escalate_7'  => ['dir_legal'],
    'escalate_15' => ['dir_admin'],
    'escalate_30' => ['secretary', 'deputy_secretary'],
];

// โหลด users ที่ active ทั้งหมดล่วงหน้า (id, email, display_name, role, officer_id)
$allUsers = $db->query("
    SELECT id, display_name, email, role, officer_id
    FROM users WHERE active = 1
")->fetchAll();
$userByOfficer = [];
$usersByRole   = [];
foreach ($allUsers as $u) {
    if ($u['officer_id']) $userByOfficer[$u['officer_id']] = $u;
    $usersByRole[$u['role']][] = $u;
}

/* ════════════════════════════════════════════════════════
   วนสำนวน
════════════════════════════════════════════════════════ */
foreach ($cases as $c) {
    $caseId  = $c['id'];
    $slaDays = (int)$c['sla_days'];
    $subject = mb_substr($c['subject'] ?? $caseId, 0, 60);

    /* หา user ที่รับผิดชอบ */
    $assigneeUser = $c['assignee_id'] ? ($userByOfficer[$c['assignee_id']] ?? null) : null;

    /* คำนวณวันคงเหลือ/เกินกำหนด */
    if (!$c['due_date']) continue; // ยังไม่ได้กำหนด due_date ข้าม
    $due          = new DateTime($c['due_date']);
    $diffDays     = (int)$today->diff($due)->days;
    $remaining    = ($due >= $today) ? $diffDays : -$diffDays; // บวก=เหลือ, ลบ=เกิน

    logLine("  {$caseId}: due={$c['due_date']} remaining={$remaining}d sla={$slaDays}d assignee={$c['assignee_id']}");

    if (!$assigneeUser) {
        // ไม่มีผู้รับผิดชอบ — ข้ามการแจ้งก่อนกำหนดและเกินกำหนด แต่ยังส่ง escalation ได้
    }

    $uid   = $assigneeUser ? (int)$assigneeUser['id'] : null;
    $email = $assigneeUser['email'] ?? null;
    $name  = $assigneeUser['display_name'] ?? null;

    /* ── ก่อนครบกำหนด ── */
    if ($remaining > 0 && $uid) {
        $pre = [
            'pre_14' => ['days' => 14, 'label' => '14 วัน'],
            'pre_7'  => ['days' => 7,  'label' => '7 วัน'],
            'pre_3'  => ['days' => 3,  'label' => '3 วัน'],
            'pre_1'  => ['days' => 1,  'label' => '1 วัน'],
        ];
        foreach ($pre as $type => $cfg) {
            if ($slaDays < $cfg['days']) continue; // SLA สั้นกว่า threshold ข้าม
            if ($remaining <= $cfg['days'] && $remaining > ($cfg['days'] - 1)) {
                $title = "⏰ ใกล้ครบกำหนด: {$subject}";
                $body  = "สำนวน {$caseId} จะครบกำหนดในอีก {$cfg['label']} (วันที่ {$c['due_date']})";
                notify($db, $uid, $caseId, $type, $title, $body, $email, $name);
            }
        }
    }

    /* ── เกินกำหนด ── */
    if ($remaining < 0 && $uid) {
        $overDays = abs($remaining);

        $over = [
            'over_1' => ['min' => 1,  'max' => 2],
            'over_3' => ['min' => 3,  'max' => 6],
            'over_7' => ['min' => 7,  'max' => PHP_INT_MAX],
        ];
        foreach ($over as $type => $cfg) {
            if ($overDays >= $cfg['min'] && $overDays < $cfg['max']) {
                $title = "🚨 เกินกำหนด {$overDays} วัน: {$subject}";
                $body  = "สำนวน {$caseId} เกินกำหนดแล้ว {$overDays} วัน (ครบกำหนดวันที่ {$c['due_date']})";
                notify($db, $uid, $caseId, $type, $title, $body, $email, $name);
                break;
            }
        }

        // over_weekly: ทุกสัปดาห์หลังจาก 7 วัน
        if ($overDays >= 7) {
            $title = "📌 แจ้งเตือนซ้ำ – เกินกำหนด {$overDays} วัน: {$subject}";
            $body  = "สำนวน {$caseId} ยังค้างอยู่และเกินกำหนดแล้ว {$overDays} วัน";
            notify($db, $uid, $caseId, 'over_weekly', $title, $body, $email, $name);
        }
    }

    /* ── Escalation (ตามสายบังคับบัญชา) ── */
    if ($remaining < 0) {
        $overDays = abs($remaining);
        $escs = [];
        if ($overDays >= 30) $escs[] = 'escalate_30';
        if ($overDays >= 15) $escs[] = 'escalate_15';
        if ($overDays >= 7)  $escs[] = 'escalate_7';

        foreach ($escs as $type) {
            $roles = $escalateRoles[$type];
            $label = ['escalate_7'=>'7','escalate_15'=>'15','escalate_30'=>'30'][$type];
            foreach ($roles as $role) {
                foreach (($usersByRole[$role] ?? []) as $boss) {
                    $bossId = (int)$boss['id'];
                    $title  = "⚠️ Escalate – สำนวนเกินกำหนด {$overDays} วัน: {$subject}";
                    $body   = "สำนวน {$caseId} เกินกำหนดแล้ว {$overDays} วัน " .
                              "เจ้าหน้าที่: " . ($name ?? 'ไม่ระบุ');
                    notify($db, $bossId, $caseId, $type, $title, $body,
                           $boss['email'] ?? null, $boss['display_name']);
                }
            }
        }
    }
}

logLine(sprintf('[%s] cron เสร็จสิ้น', date('Y-m-d H:i:s')));

if (!$isCli) {
    echo implode("\n", $log) . "\n";
}
