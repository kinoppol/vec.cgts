<?php
/**
 * migrate.php — migrations รวม
 *   [1] รัน personnel.sql และตั้งรหัสผ่านใหม่   ?confirm=run&pass=xxx
 *   [2] เพิ่มตาราง sla_steps + คอลัมน์ case_events  ?confirm=sla
 *   [3] เพิ่ม attachment columns ใน case_events        ?confirm=event_attach
 */
require_once __DIR__ . '/../config/db.php';

$confirm = $_GET['confirm'] ?? '';

/* ── [3] Event Attachment columns ──────────────────────── */
if ($confirm === 'event_attach') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration: Event Attachment Columns</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $cols = $db->query("SHOW COLUMNS FROM case_events")->fetchAll(PDO::FETCH_COLUMN);
        foreach ([
            'attachment_name VARCHAR(300) DEFAULT NULL AFTER detail',
            'attachment_path VARCHAR(300) DEFAULT NULL AFTER attachment_name',
            'attachment_size VARCHAR(30)  DEFAULT NULL AFTER attachment_path',
        ] as $col) {
            $name = explode(' ', $col)[0];
            if (!in_array($name, $cols)) {
                $db->exec("ALTER TABLE case_events ADD COLUMN $col");
                echo "✓ ALTER case_events ADD $name\n";
            } else {
                echo "– $name มีอยู่แล้ว ข้าม\n";
            }
        }
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [2] SLA Steps migration ────────────────────────────── */
if ($confirm === 'sla') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration: SLA Steps</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("
            CREATE TABLE IF NOT EXISTS sla_steps (
              id           INT          NOT NULL AUTO_INCREMENT,
              step_key     VARCHAR(50)  NOT NULL,
              label        VARCHAR(200) NOT NULL,
              days_allowed INT          NOT NULL DEFAULT 1,
              sort_order   SMALLINT     NOT NULL DEFAULT 0,
              active       TINYINT(1)   NOT NULL DEFAULT 1,
              note         VARCHAR(300) DEFAULT NULL,
              updated_by   INT          DEFAULT NULL,
              updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_step_key (step_key),
              CONSTRAINT fk_slastep_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ CREATE TABLE sla_steps (IF NOT EXISTS)\n";

        $cols = $db->query("SHOW COLUMNS FROM case_events")->fetchAll(PDO::FETCH_COLUMN);

        foreach (['step_key VARCHAR(50) DEFAULT NULL AFTER sort_order',
                  'started_at DATE DEFAULT NULL AFTER step_key',
                  'completed_at DATE DEFAULT NULL AFTER started_at'] as $col) {
            $name = explode(' ', $col)[0];
            if (!in_array($name, $cols)) {
                $db->exec("ALTER TABLE case_events ADD COLUMN $col");
                echo "✓ ALTER case_events ADD $name\n";
            } else {
                echo "– $name มีอยู่แล้ว ข้าม\n";
            }
        }

        $db->exec("
            INSERT INTO sla_steps (step_key, label, days_allowed, sort_order, note) VALUES
              ('receive',      'รับเรื่อง',             1,  10, 'นับจากวันที่ประชาชนยื่นเรื่อง'),
              ('propose_dir',  'เสนอ ผอ.สำนัก',        2,  20, 'เสนอผู้อำนวยการสำนักอำนวยการพิจารณา'),
              ('assign',       'มอบหมายนิติกร',          1,  30, 'มอบหมายเจ้าหน้าที่นิติกรเจ้าของเรื่อง'),
              ('investigate',  'ตรวจข้อเท็จจริง',       15, 40, 'นิติกรดำเนินการตรวจสอบและรวบรวมพยานหลักฐาน'),
              ('propose_boss', 'เสนอผู้บังคับบัญชา',    5,  50, 'เสนอสายบังคับบัญชาเพื่อพิจารณาสั่งการ'),
              ('order',        'ออกคำสั่ง',             3,  60, 'ออกหนังสือคำสั่ง/แจ้งผลการพิจารณา')
            ON DUPLICATE KEY UPDATE
              label=VALUES(label), days_allowed=VALUES(days_allowed),
              sort_order=VALUES(sort_order), note=VALUES(note)
        ");
        $count = $db->query("SELECT COUNT(*) FROM sla_steps")->fetchColumn();
        echo "✓ INSERT/UPDATE sla_steps ($count ขั้นตอน)\n";
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [1] Personnel + password (เดิม) ────────────────────── */
// ป้องกันการเรียกโดยไม่ตั้งใจ
if ($confirm !== 'run') {
    echo '<style>body{font-family:sans-serif;padding:24px}code{background:#f5f5f5;padding:2px 6px;border-radius:4px}li{margin:8px 0}</style>';
    echo '<h2>Migration</h2><ul>';
    echo '<li><b>[1] Personnel + รหัสผ่าน</b><br><code><a href="?confirm=run&pass=password">migrate.php?confirm=run&pass=password</a></code></li>';
    echo '<li><b>[2] SLA Steps</b> — เพิ่มตาราง sla_steps และคอลัมน์ case_events<br><code><a href="?confirm=sla">migrate.php?confirm=sla</a></code></li>';
    echo '<li><b>[3] Event Attachment</b> — เพิ่มคอลัมน์ attachment_name/path/size ใน case_events<br><code><a href="?confirm=event_attach">migrate.php?confirm=event_attach</a></code></li>';
    echo '</ul>';
    exit;
}

$newPass = $_GET['pass'] ?? '';
if ($newPass === '') {
    die('ต้องระบุ ?pass=รหัสผ่าน');
}

try {
    $db = getDB();

    // รัน personnel.sql
    $sql = file_get_contents(__DIR__ . '/personnel.sql');
    // ลบ comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $sql = preg_replace('/--[^\n]*/', '', $sql);

    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    $ran = 0;
    foreach ($stmts as $stmt) {
        if (preg_match('/^\s*USE\s+/i', $stmt)) continue;
        $db->exec($stmt);
        $ran++;
    }

    // ตั้งรหัสผ่านทุก account
    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $affected = $db->prepare('UPDATE users SET password_hash = ?')->execute([$hash]);

    // แสดงผล users ทั้งหมด
    $users = $db->query('SELECT username, display_name, role, active FROM users ORDER BY role, username')->fetchAll();

    echo '<style>body{font-family:sans-serif;padding:24px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:8px 12px;text-align:left}th{background:#f5f5f5}.ok{color:green}.err{color:red}</style>';
    echo '<h2 class="ok">✓ Migration สำเร็จ</h2>';
    echo "<p>รัน {$ran} statements จาก personnel.sql</p>";
    echo "<p>ตั้งรหัสผ่าน <b>" . htmlspecialchars($newPass) . "</b> ให้ทุกบัญชีแล้ว</p>";
    echo '<table><tr><th>username</th><th>display_name</th><th>role</th><th>active</th></tr>';
    foreach ($users as $u) {
        echo "<tr><td><code>{$u['username']}</code></td><td>{$u['display_name']}</td><td>{$u['role']}</td><td>" . ($u['active'] ? '✓' : '✗') . "</td></tr>";
    }
    echo '</table>';
    echo '<p style="color:red"><b>⚠ ลบไฟล์นี้ออกหลังใช้งาน:</b> <code>db/migrate.php</code></p>';

} catch (Throwable $e) {
    echo '<p style="color:red">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
