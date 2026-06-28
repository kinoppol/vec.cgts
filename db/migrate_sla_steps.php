<?php
/**
 * Migration: เพิ่มตาราง sla_steps และคอลัมน์ใน case_events
 * เรียกใช้: http://<domain>/vec.cgts/db/migrate_sla_steps.php?confirm=run
 * ลบไฟล์นี้หลังรันเสร็จแล้ว!
 */
date_default_timezone_set('Asia/Bangkok');

$token = $_GET['confirm'] ?? '';
if ($token !== 'run') {
    echo "<pre>เรียกใช้: migrate_sla_steps.php?confirm=run\n\nคำเตือน: ไฟล์นี้จะแก้ไขโครงสร้างตาราง case_events และเพิ่มตาราง sla_steps\nปลอดภัย — ใช้ CREATE TABLE IF NOT EXISTS และ ALTER TABLE IF NOT EXISTS (idempotent)</pre>";
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<pre>";

    // 1. สร้างตาราง sla_steps
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

    // 2. เพิ่มคอลัมน์ใน case_events (ตรวจสอบก่อนว่ามีหรือยัง)
    $cols = $db->query("SHOW COLUMNS FROM case_events")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('step_key', $cols)) {
        $db->exec("ALTER TABLE case_events ADD COLUMN step_key VARCHAR(50) DEFAULT NULL AFTER sort_order");
        echo "✓ ALTER case_events ADD step_key\n";
    } else {
        echo "– step_key มีอยู่แล้ว ข้าม\n";
    }

    if (!in_array('started_at', $cols)) {
        $db->exec("ALTER TABLE case_events ADD COLUMN started_at DATE DEFAULT NULL AFTER step_key");
        echo "✓ ALTER case_events ADD started_at\n";
    } else {
        echo "– started_at มีอยู่แล้ว ข้าม\n";
    }

    if (!in_array('completed_at', $cols)) {
        $db->exec("ALTER TABLE case_events ADD COLUMN completed_at DATE DEFAULT NULL AFTER started_at");
        echo "✓ ALTER case_events ADD completed_at\n";
    } else {
        echo "– completed_at มีอยู่แล้ว ข้าม\n";
    }

    // 3. ข้อมูลเริ่มต้น 6 ขั้นตอน
    $db->exec("
        INSERT INTO sla_steps (step_key, label, days_allowed, sort_order, note) VALUES
          ('receive',      'รับเรื่อง',             1,  10, 'นับจากวันที่ประชาชนยื่นเรื่อง'),
          ('propose_dir',  'เสนอ ผอ.สำนัก',        2,  20, 'เสนอผู้อำนวยการสำนักอำนวยการพิจารณา'),
          ('assign',       'มอบหมายนิติกร',          1,  30, 'มอบหมายเจ้าหน้าที่นิติกรเจ้าของเรื่อง'),
          ('investigate',  'ตรวจข้อเท็จจริง',       15, 40, 'นิติกรดำเนินการตรวจสอบและรวบรวมพยานหลักฐาน'),
          ('propose_boss', 'เสนอผู้บังคับบัญชา',    5,  50, 'เสนอสายบังคับบัญชาเพื่อพิจารณาสั่งการ'),
          ('order',        'ออกคำสั่ง',             3,  60, 'ออกหนังสือคำสั่ง/แจ้งผลการพิจารณา')
        ON DUPLICATE KEY UPDATE
          label        = VALUES(label),
          days_allowed = VALUES(days_allowed),
          sort_order   = VALUES(sort_order),
          note         = VALUES(note)
    ");
    echo "✓ INSERT/UPDATE sla_steps (6 ขั้นตอน)\n";

    // ตรวจสอบผลลัพธ์
    $count = $db->query("SELECT COUNT(*) FROM sla_steps")->fetchColumn();
    echo "\n✅ Migration สำเร็จ — sla_steps มี {$count} ขั้นตอน\n";
    echo "\n⚠️  กรุณาลบไฟล์ migrate_sla_steps.php หลังรันเสร็จแล้ว!\n";
    echo "</pre>";

} catch (Throwable $e) {
    echo "<pre>❌ ข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</pre>";
}
