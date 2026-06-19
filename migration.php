<?php
/**
 * migration.php — อัปเดตฐานข้อมูล vec_cgts (2026-06-19)
 * เรียกใช้ครั้งเดียว แล้วลบหรือ block ไฟล์นี้
 *
 * การเปลี่ยนแปลง:
 *   1. สร้างตาราง todo_items (รายการที่ต้องทำ สำหรับ admin)
 *   2. เพิ่ม ENUM role: secretary, deputy_secretary
 *   3. เพิ่มผู้ใช้ เลขาธิการ และ รองเลขาธิการ สอศ. (ถ้ายังไม่มี)
 *   4. สร้างตาราง sla_settings พร้อมข้อมูลเริ่มต้น
 */

date_default_timezone_set('Asia/Bangkok');
ini_set('display_errors', '0');

define('CONFIG_PATH', __DIR__ . '/config/db.php');

/* --------------------------------------------------------
   ตรวจสอบ config
-------------------------------------------------------- */
if (!file_exists(CONFIG_PATH)) {
    http_response_code(500);
    die('<pre style="color:red">❌ ไม่พบ config/db.php — กรุณารัน install.php ก่อน</pre>');
}
require_once CONFIG_PATH;

/* --------------------------------------------------------
   AJAX handler
-------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'migrate') {
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
    $log = [];

    try {
        $pdo = getDB();
        $log[] = ['ok' => true, 'msg' => 'เชื่อมต่อฐานข้อมูล ' . DB_NAME . ' สำเร็จ'];

        /* ---- ขั้นตอนที่ 1: ตาราง todo_items ---- */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS todo_items (
              id           INT          NOT NULL AUTO_INCREMENT,
              user_id      INT          NOT NULL,
              title        VARCHAR(300) NOT NULL,
              detail       TEXT         DEFAULT NULL,
              done         TINYINT(1)   NOT NULL DEFAULT 0,
              created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              completed_at TIMESTAMP    NULL DEFAULT NULL,
              PRIMARY KEY (id),
              KEY idx_todo_user (user_id),
              CONSTRAINT fk_todo_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $log[] = ['ok' => true, 'msg' => 'สร้าง / ยืนยันตาราง todo_items สำเร็จ'];

        /* ---- ขั้นตอนที่ 2: เพิ่ม ENUM role ใหม่ ---- */
        $pdo->exec("
            ALTER TABLE users
              MODIFY COLUMN role
                ENUM('officer','dir_legal','dir_admin','secretary','deputy_secretary','admin')
                NOT NULL DEFAULT 'officer'
        ");
        $log[] = ['ok' => true, 'msg' => 'อัปเดต ENUM role (เพิ่ม secretary, deputy_secretary) สำเร็จ'];

        /* ---- ขั้นตอนที่ 3: เพิ่มผู้ใช้ใหม่ ---- */
        $hash = '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy'; // "password"
        $users = [
            ['yospol',     $hash, 'นายยศพล เวณุโกเศศ',           'secretary',        'ยศ'],
            ['withawat',   $hash, 'นายวิทวัส ปัญจมะวัต',         'deputy_secretary', 'วท'],
            ['sanga',      $hash, 'นายสง่า แต่เชื้อสาย',         'deputy_secretary', 'สง'],
            ['narongchai', $hash, 'นายณรงค์ชัย เจริญรุจิทรัพย์', 'deputy_secretary', 'ณช'],
        ];
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, display_name, role, init, officer_id, can_manage_users)
            VALUES (?, ?, ?, ?, ?, NULL, 0)
            ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), role=VALUES(role), init=VALUES(init)
        ");
        foreach ($users as $u) {
            $stmt->execute($u);
        }
        $log[] = ['ok' => true, 'msg' => 'เพิ่ม / อัปเดตผู้ใช้ 4 บัญชี (เลขาธิการ + รองเลขาธิการ) สำเร็จ'];

        /* ---- ขั้นตอนที่ 4: ตาราง sla_settings + ข้อมูลเริ่มต้น ---- */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sla_settings (
              id          INT          NOT NULL AUTO_INCREMENT,
              track       ENUM('discipline','legal') NOT NULL,
              cat         VARCHAR(100) NOT NULL,
              days        INT          NOT NULL DEFAULT 30,
              note        VARCHAR(300) DEFAULT NULL,
              updated_by  INT          DEFAULT NULL,
              updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_sla_track_cat (track, cat),
              CONSTRAINT fk_sla_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            INSERT INTO sla_settings (track, cat, days, note) VALUES
              ('discipline','งานร้องเรียน',           90, NULL),
              ('discipline','งานวินัย',               120, NULL),
              ('discipline','งานอุทธรณ์',              60, NULL),
              ('discipline','งานร้องทุกข์',            90, NULL),
              ('legal','ระเบียบ/กฎหมาย/คำสั่ง',       30, NULL),
              ('legal','นิติกรรมสัญญา',                30, NULL),
              ('legal','คดีปกครอง/แพ่ง/อาญา',         60, NULL),
              ('legal','ความรับผิดทางละเมิด',          90, NULL)
            ON DUPLICATE KEY UPDATE days=VALUES(days)
        ");
        $log[] = ['ok' => true, 'msg' => 'สร้าง / ยืนยันตาราง sla_settings พร้อมข้อมูลเริ่มต้น 8 หมวด สำเร็จ'];

        /* ---- ขั้นตอนที่ 5: เพิ่มคอลัมน์ job_title, group_name ใน users ---- */
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS job_title  VARCHAR(200) DEFAULT NULL AFTER init");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS group_name VARCHAR(200) DEFAULT NULL AFTER job_title");
        $log[] = ['ok' => true, 'msg' => 'เพิ่มคอลัมน์ job_title, group_name ใน users สำเร็จ'];

        /* ---- ขั้นตอนที่ 6: สร้างตาราง role_labels + ข้อมูลเริ่มต้น ---- */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS role_labels (
              role  VARCHAR(50)  NOT NULL,
              label VARCHAR(200) NOT NULL,
              PRIMARY KEY (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            INSERT INTO role_labels (role, label) VALUES
              ('officer',          'เจ้าหน้าที่นิติการ / ธุรการ'),
              ('dir_legal',        'ผอ.กลุ่มนิติการ'),
              ('dir_admin',        'ผอ.สำนักอำนวยการ'),
              ('secretary',        'เลขาธิการ สอศ.'),
              ('deputy_secretary', 'รองเลขาธิการ สอศ.'),
              ('admin',            'ผู้ดูแลระบบ')
            ON DUPLICATE KEY UPDATE label=VALUES(label)
        ");
        $log[] = ['ok' => true, 'msg' => 'สร้าง / ยืนยันตาราง role_labels พร้อมข้อมูลเริ่มต้น 6 บทบาท สำเร็จ'];

        /* ---- ขั้นตอนที่ 7: เพิ่มคอลัมน์ duty และ active ใน officers ---- */
        $pdo->exec("ALTER TABLE officers ADD COLUMN IF NOT EXISTS duty   VARCHAR(200) DEFAULT NULL AFTER job_title");
        $pdo->exec("ALTER TABLE officers ADD COLUMN IF NOT EXISTS active TINYINT(1)   NOT NULL DEFAULT 1 AFTER init");
        $log[] = ['ok' => true, 'msg' => 'เพิ่มคอลัมน์ duty, active ใน officers สำเร็จ'];

        /* ---- ขั้นตอนที่ 8: สร้างตาราง lookup_items + ข้อมูลเริ่มต้น ---- */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lookup_items (
              id         INT          NOT NULL AUTO_INCREMENT,
              category   VARCHAR(50)  NOT NULL,
              name       VARCHAR(200) NOT NULL,
              sort_order SMALLINT     NOT NULL DEFAULT 0,
              active     TINYINT(1)   NOT NULL DEFAULT 1,
              PRIMARY KEY (id),
              KEY idx_lookup_cat (category, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $pdo->exec("
            INSERT INTO lookup_items (category, name, sort_order) VALUES
              ('group_name', 'กลุ่มงานกฎหมายและระเบียบ', 1),
              ('group_name', 'กลุ่มงานวินัย',             2),
              ('group_name', 'ฝ่ายบริหารงานทั่วไป',       3),
              ('job_title',  'นิติกรชำนาญการพิเศษ',       1),
              ('job_title',  'นิติกรชำนาญการ',            2),
              ('job_title',  'นิติกรปฏิบัติการ',          3),
              ('job_title',  'พนักงานบริหารทั่วไป',       4),
              ('job_title',  'นักวิชาการศึกษา',           5)
            ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order)
        ");
        $log[] = ['ok' => true, 'msg' => 'สร้าง / ยืนยันตาราง lookup_items พร้อมข้อมูลเริ่มต้น 8 รายการ สำเร็จ'];

        ob_end_clean();
        echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        $stray = ob_get_clean();
        $log[] = ['ok' => false, 'msg' => $e->getMessage() . ($stray ? ' | ' . trim($stray) : '')];
        echo json_encode(['ok' => false, 'log' => $log], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* --------------------------------------------------------
   ตรวจสอบสถานะปัจจุบัน (เพื่อแสดงก่อนรัน)
-------------------------------------------------------- */
$status = ['todo' => false, 'enum' => false, 'users' => 0, 'sla' => 0];
try {
    $pdo = getDB();

    // ตรวจ todo_items
    $r = $pdo->query("SHOW TABLES LIKE 'todo_items'")->fetch();
    $status['todo'] = (bool)$r;

    // ตรวจ ENUM มี secretary หรือไม่
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    $status['enum'] = $col && str_contains($col['Type'] ?? '', 'secretary');

    // ตรวจผู้ใช้
    $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('secretary','deputy_secretary')")->fetchColumn();
    $status['users'] = (int)$cnt;

    // ตรวจ sla_settings
    $r2 = $pdo->query("SHOW TABLES LIKE 'sla_settings'")->fetch();
    if ($r2) {
        $status['sla'] = (int)$pdo->query("SELECT COUNT(*) FROM sla_settings")->fetchColumn();
    }

    // ตรวจคอลัมน์ job_title, group_name
    $cols = $pdo->query("SHOW COLUMNS FROM users WHERE Field IN ('job_title','group_name')")->fetchAll();
    $status['profile_cols'] = count($cols) >= 2;

    // ตรวจ role_labels
    $r3 = $pdo->query("SHOW TABLES LIKE 'role_labels'")->fetch();
    if ($r3) {
        $status['role_labels'] = (int)$pdo->query("SELECT COUNT(*) FROM role_labels")->fetchColumn();
    } else {
        $status['role_labels'] = 0;
    }

    // ตรวจคอลัมน์ duty, active ใน officers
    $ocols = $pdo->query("SHOW COLUMNS FROM officers WHERE Field IN ('duty','active')")->fetchAll();
    $status['officer_cols'] = count($ocols) >= 2;

    // ตรวจ lookup_items
    $r4 = $pdo->query("SHOW TABLES LIKE 'lookup_items'")->fetch();
    if ($r4) {
        $status['lookup_items'] = (int)$pdo->query("SELECT COUNT(*) FROM lookup_items WHERE active=1")->fetchColumn();
    } else {
        $status['lookup_items'] = 0;
    }

} catch (Throwable) {}

$allDone = $status['todo'] && $status['enum'] && $status['users'] >= 4 && $status['sla'] >= 8
        && $status['profile_cols'] && $status['role_labels'] >= 6
        && $status['officer_cols'] && $status['lookup_items'] >= 8;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Migration 2026-06-19 — สอศ.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --maroon: #7A1E2A; --maroon-d: #5c1620;
    --bg: #f5f4f2; --surface: #fff; --surface-2: #f9f8f6;
    --ink: #1a1a1a; --ink-2: #444; --ink-3: #888;
    --line: #e5e2dd; --radius: 12px;
    --ok: #1e7e34; --ok-bg: #d4edda; --ok-border: #b8dfc2;
    --err: #a31515; --err-bg: #fde8e8; --err-border: #f5c6c6;
    --warn: #856404; --warn-bg: #fff3cd; --warn-border: #ffd97a;
    --info: #0c5460; --info-bg: #d1ecf1; --info-border: #b8d8df;
    --fs: 'Sarabun', system-ui, sans-serif;
    --shadow: 0 4px 24px rgba(0,0,0,.08);
  }
  body { font-family: var(--fs); background: var(--bg); color: var(--ink); font-size: 15px; line-height: 1.65; min-height: 100vh; padding: 40px 16px 80px; }

  .wrap { max-width: 680px; margin: 0 auto; }

  /* header */
  .hd { display: flex; align-items: center; gap: 14px; margin-bottom: 32px; }
  .hd-logo { width: 52px; height: 52px; background: var(--maroon); border-radius: 12px;
              display: grid; place-items: center; flex-shrink: 0; }
  .hd-logo svg { width: 30px; height: 30px; }
  .hd h1 { font-size: 20px; font-weight: 700; color: var(--maroon); }
  .hd p  { font-size: 13px; color: var(--ink-3); margin-top: 2px; }

  /* card */
  .card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); padding: 28px 32px; margin-bottom: 20px; }
  .card-title { font-size: 15px; font-weight: 700; color: var(--ink-2); margin-bottom: 18px;
                padding-bottom: 12px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 8px; }

  /* migration steps */
  .steps { display: flex; flex-direction: column; gap: 12px; }
  .step-row { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 8px;
              background: var(--surface-2); border: 1px solid var(--line); }
  .step-num { width: 28px; height: 28px; border-radius: 50%; background: var(--maroon); color: #fff;
               font-size: 12px; font-weight: 700; display: grid; place-items: center; flex-shrink: 0; margin-top: 1px; }
  .step-num.done { background: var(--ok); }
  .step-body { flex: 1; }
  .step-title { font-weight: 600; font-size: 14px; }
  .step-desc  { font-size: 13px; color: var(--ink-3); margin-top: 3px; }
  .step-badge { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 20px;
                font-size: 11px; font-weight: 600; margin-top: 6px; }
  .badge-done    { background: var(--ok-bg);   color: var(--ok);  border: 1px solid var(--ok-border); }
  .badge-pending { background: var(--warn-bg); color: var(--warn);border: 1px solid var(--warn-border); }

  /* notice */
  .notice { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 8px;
            font-size: 13.5px; margin-bottom: 16px; line-height: 1.55; border: 1px solid; }
  .notice-warn { background: var(--warn-bg); color: var(--warn); border-color: var(--warn-border); }
  .notice-info { background: var(--info-bg); color: var(--info); border-color: var(--info-border); }
  .notice-ok   { background: var(--ok-bg);   color: var(--ok);  border-color: var(--ok-border); }

  /* log */
  #logBox { background: #1a1a1a; color: #d4d4d4; border-radius: 8px; padding: 16px 18px;
            font-family: 'Consolas','Monaco',monospace; font-size: 13px; line-height: 1.9;
            min-height: 80px; margin-top: 16px; display: none; }
  #logBox.show { display: block; }
  .l-ok  { color: #6ac47b; }
  .l-err { color: #f47979; }
  .l-spin{ color: #c9a84c; }

  /* buttons */
  .btn-row { display: flex; gap: 10px; margin-top: 22px; }
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px;
         padding: 11px 26px; border-radius: 8px; font-family: var(--fs); font-size: 14px;
         font-weight: 600; cursor: pointer; border: none; transition: all .15s; text-decoration: none; }
  .btn:disabled { opacity: .5; cursor: not-allowed; }
  .btn-primary { background: var(--maroon); color: #fff; }
  .btn-primary:hover:not(:disabled) { background: var(--maroon-d); }
  .btn-ghost { background: transparent; color: var(--ink-3); border: 1.5px solid var(--line); }
  .btn-ghost:hover { background: var(--surface-2); }
  .ml-auto { margin-left: auto; }
  code { background: #f0ede9; padding: 1px 6px; border-radius: 4px; font-size: 13px; font-family: monospace; }

  @keyframes spin { to { transform: rotate(360deg); } }
  .spinner { display: inline-block; width: 15px; height: 15px; border: 2px solid rgba(255,255,255,.35);
             border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="hd">
    <div class="hd-logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
    </div>
    <div>
      <h1>Migration 2026-06-19</h1>
      <p>ระบบรับเรื่องร้องเรียน–ร้องทุกข์ · สำนักงานคณะกรรมการการอาชีวศึกษา</p>
    </div>
  </div>

  <?php if ($allDone): ?>
  <!-- Already applied -->
  <div class="notice notice-ok">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="flex-shrink:0;margin-top:1px"><path d="M22 11.5V12a10 10 0 1 1-5.9-9.1M22 4L12 14.1l-3-3"/></svg>
    <div><b>อัปเดตเสร็จสิ้นแล้ว</b> — ตรวจสอบแล้วพบว่าการเปลี่ยนแปลงทั้งหมดถูกนำไปใช้กับฐานข้อมูลแล้ว
    <br>ควรลบหรือ block ไฟล์ <code>migration.php</code> เพื่อความปลอดภัย</div>
  </div>
  <?php endif; ?>

  <!-- Step list -->
  <div class="card">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12l2 2 4-4"/></svg>
      รายการที่จะอัปเดต
    </div>

    <div class="steps">

      <!-- Step 1 -->
      <div class="step-row">
        <div class="step-num <?= $status['todo'] ? 'done' : '' ?>">
          <?= $status['todo'] ? '✓' : '1' ?>
        </div>
        <div class="step-body">
          <div class="step-title">สร้างตาราง <code>todo_items</code></div>
          <div class="step-desc">ระบบรายการที่ต้องทำ สำหรับผู้ดูแลระบบ (Admin)<br>
            คอลัมน์: id, user_id, title, detail, done, created_at, completed_at</div>
          <span class="step-badge <?= $status['todo'] ? 'badge-done' : 'badge-pending' ?>">
            <?= $status['todo'] ? '✓ มีตารางนี้แล้ว' : '⏳ ยังไม่ได้สร้าง' ?>
          </span>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="step-row">
        <div class="step-num <?= $status['enum'] ? 'done' : '' ?>">
          <?= $status['enum'] ? '✓' : '2' ?>
        </div>
        <div class="step-body">
          <div class="step-title">เพิ่ม <code>role</code> ใหม่ใน ENUM</div>
          <div class="step-desc">เพิ่ม <code>secretary</code> และ <code>deputy_secretary</code>
            เข้าไปใน <code>users.role</code><br>
            บทบาทเหมือน ผอ.สำนักอำนวยการ แยกประเภทรองรับสิทธิ์ในอนาคต</div>
          <span class="step-badge <?= $status['enum'] ? 'badge-done' : 'badge-pending' ?>">
            <?= $status['enum'] ? '✓ มี ENUM นี้แล้ว' : '⏳ ยังไม่ได้เพิ่ม' ?>
          </span>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="step-row">
        <div class="step-num <?= $status['users'] >= 4 ? 'done' : '' ?>">
          <?= $status['users'] >= 4 ? '✓' : '3' ?>
        </div>
        <div class="step-body">
          <div class="step-title">เพิ่มบัญชีผู้ใช้ เลขาธิการ และ รองเลขาธิการ</div>
          <div class="step-desc">
            เลขาธิการ: <b>นายยศพล เวณุโกเศศ</b> (<code>yospol</code>)<br>
            รองเลขาธิการ: <b>นายวิทวัส ปัญจมะวัต</b> (<code>withawat</code>),
              <b>นายสง่า แต่เชื้อสาย</b> (<code>sanga</code>),
              <b>นายณรงค์ชัย เจริญรุจิทรัพย์</b> (<code>narongchai</code>)<br>
            <span style="font-size:12px;color:var(--warn)">รหัสผ่านเริ่มต้น: <code>password</code> — กรุณาเปลี่ยนหลังเข้าสู่ระบบ</span>
          </div>
          <span class="step-badge <?= $status['users'] >= 4 ? 'badge-done' : 'badge-pending' ?>">
            <?= $status['users'] >= 4 ? "✓ มีผู้ใช้ {$status['users']} บัญชีแล้ว" : "⏳ พบ {$status['users']} บัญชี (ต้องการ 4)" ?>
          </span>
        </div>
      </div>

      <!-- Step 4 -->
      <div class="step-row">
        <div class="step-num <?= $status['sla'] >= 8 ? 'done' : '' ?>">
          <?= $status['sla'] >= 8 ? '✓' : '4' ?>
        </div>
        <div class="step-body">
          <div class="step-title">สร้างตาราง <code>sla_settings</code> พร้อมข้อมูลเริ่มต้น</div>
          <div class="step-desc">
            ตั้งค่าระยะเวลา SLA (จำนวนวัน) สำหรับแต่ละสายงานและหมวดงาน<br>
            ค่าเริ่มต้น 8 หมวด: งานร้องเรียน/วินัย/อุทธรณ์/ร้องทุกข์ และ ระเบียบ/สัญญา/คดี/ละเมิด<br>
            ปรับได้โดย <b>ผอ.กลุ่มนิติการ</b> และ <b>ผู้ดูแลระบบ</b> ในเมนู "ตั้งค่า SLA"
          </div>
          <span class="step-badge <?= $status['sla'] >= 8 ? 'badge-done' : 'badge-pending' ?>">
            <?= $status['sla'] >= 8 ? "✓ มีข้อมูล {$status['sla']} หมวดแล้ว" : "⏳ พบ {$status['sla']} หมวด (ต้องการ 8)" ?>
          </span>
        </div>
      </div>

      <!-- Step 5 -->
      <div class="step-row">
        <div class="step-num <?= $status['profile_cols'] ? 'done' : '' ?>">
          <?= $status['profile_cols'] ? '✓' : '5' ?>
        </div>
        <div class="step-body">
          <div class="step-title">เพิ่มคอลัมน์ <code>job_title</code> และ <code>group_name</code> ใน <code>users</code></div>
          <div class="step-desc">เก็บข้อมูลตำแหน่งและชื่อกลุ่ม/หน่วยงานของผู้ใช้แต่ละบัญชี<br>
            จัดการได้โดย Admin ในหน้า "จัดการผู้ใช้งาน"</div>
          <span class="step-badge <?= $status['profile_cols'] ? 'badge-done' : 'badge-pending' ?>">
            <?= $status['profile_cols'] ? '✓ มีคอลัมน์นี้แล้ว' : '⏳ ยังไม่ได้เพิ่ม' ?>
          </span>
        </div>
      </div>

      <!-- Step 6 -->
      <div class="step-row">
        <div class="step-num <?= $status['role_labels'] >= 6 ? 'done' : '' ?>">
          <?= $status['role_labels'] >= 6 ? '✓' : '6' ?>
        </div>
        <div class="step-body">
          <div class="step-title">สร้างตาราง <code>role_labels</code> พร้อมชื่อบทบาทเริ่มต้น</div>
          <div class="step-desc">
            เก็บชื่อที่แสดงสำหรับแต่ละบทบาทผู้ใช้งาน (6 บทบาท)<br>
            ปรับได้โดย <b>ผู้ดูแลระบบ</b> ในเมนู "ชื่อบทบาท"
          </div>
          <span class="step-badge <?= $status['role_labels'] >= 6 ? 'badge-done' : 'badge-pending' ?>">
            <?= $status['role_labels'] >= 6 ? "✓ มีข้อมูล {$status['role_labels']} บทบาทแล้ว" : "⏳ พบ {$status['role_labels']} บทบาท (ต้องการ 6)" ?>
          </span>
        </div>
      </div>

      <!-- Step 7 -->
      <div class="step-row">
        <div class="step-num <?= !empty($status['officer_cols']) ? 'done' : '' ?>">
          <?= !empty($status['officer_cols']) ? '✓' : '7' ?>
        </div>
        <div class="step-body">
          <div class="step-title">เพิ่มคอลัมน์ <code>duty</code> และ <code>active</code> ใน <code>officers</code></div>
          <div class="step-desc">
            <code>duty</code> — หน้าที่/ตำแหน่งในหน้าที่ (กรณีมีตำแหน่งบริหารเพิ่มเติม)<br>
            <code>active</code> — สถานะปฏิบัติงาน (0 = ไม่ active) จัดการได้ในเมนู "จัดการบุคลากร"
          </div>
          <span class="step-badge <?= !empty($status['officer_cols']) ? 'badge-done' : 'badge-pending' ?>">
            <?= !empty($status['officer_cols']) ? '✓ มีคอลัมน์นี้แล้ว' : '⏳ ยังไม่ได้เพิ่ม' ?>
          </span>
        </div>
      </div>

      <!-- Step 8 -->
      <div class="step-row">
        <div class="step-num <?= $status['lookup_items'] >= 8 ? 'done' : '' ?>">
          <?= $status['lookup_items'] >= 8 ? '✓' : '8' ?>
        </div>
        <div class="step-body">
          <div class="step-title">สร้างตาราง <code>lookup_items</code> พร้อมรายการอ้างอิงเริ่มต้น</div>
          <div class="step-desc">
            เก็บรายการ dropdown สำหรับ <b>ชื่อกลุ่มงาน</b> (3 รายการ) และ <b>ชื่อตำแหน่ง</b> (5 รายการ)<br>
            จัดการได้โดย <b>ผู้ดูแลระบบ</b> ในเมนู "รายการอ้างอิง"
          </div>
          <span class="step-badge <?= $status['lookup_items'] >= 8 ? 'badge-done' : 'badge-pending' ?>">
            <?= $status['lookup_items'] >= 8 ? "✓ มีรายการ {$status['lookup_items']} รายการแล้ว" : "⏳ พบ {$status['lookup_items']} รายการ (ต้องการ 8)" ?>
          </span>
        </div>
      </div>

    </div><!-- /.steps -->
  </div>

  <!-- Run card -->
  <div class="card" id="runCard">
    <?php if ($allDone): ?>
      <div class="notice notice-ok" style="margin-bottom:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="flex-shrink:0"><path d="M22 11.5V12a10 10 0 1 1-5.9-9.1M22 4L12 14.1l-3-3"/></svg>
        <div>ฐานข้อมูลเป็นเวอร์ชันล่าสุดแล้ว ไม่จำเป็นต้องรันซ้ำ</div>
      </div>
      <div class="btn-row">
        <a href="index.php" class="btn btn-primary ml-auto">เข้าสู่ระบบ →</a>
      </div>
    <?php else: ?>
      <div class="notice notice-warn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="flex-shrink:0;margin-top:1px"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/></svg>
        <div>การดำเนินการนี้จะแก้ไขโครงสร้างฐานข้อมูล <b><?= htmlspecialchars(DB_NAME) ?></b>
          ควรสำรองข้อมูลก่อนหากมีข้อมูลสำคัญ</div>
      </div>

      <div id="logBox"></div>

      <div class="btn-row">
        <a href="index.php" class="btn btn-ghost">← กลับหน้าหลัก</a>
        <button id="btnRun" class="btn btn-primary ml-auto" onclick="runMigration()">
          <span id="btnTxt">🚀 รัน Migration</span>
        </button>
      </div>
    <?php endif; ?>
  </div>

  <!-- Security notice -->
  <div class="notice notice-info" style="font-size:13px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
    <div>เพื่อความปลอดภัย กรุณาลบหรือเปลี่ยนชื่อไฟล์ <code>migration.php</code> หลังรันเสร็จแล้ว</div>
  </div>

</div><!-- /.wrap -->

<script>
async function runMigration() {
  const btn    = document.getElementById('btnRun');
  const txt    = document.getElementById('btnTxt');
  const logBox = document.getElementById('logBox');

  btn.disabled = true;
  txt.innerHTML = '<span class="spinner"></span> กำลังอัปเดต…';
  logBox.className = 'show';
  logBox.innerHTML = '<span class="l-spin">⟳ เริ่มต้น migration…</span>';

  try {
    const r = await fetch('migration.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=migrate'
    });
    const d = await r.json();

    const lines = (d.log || []).map(l =>
      `<div class="${l.ok ? 'l-ok' : 'l-err'}">${l.ok ? '✓' : '✗'} ${esc(l.msg)}</div>`
    ).join('');
    logBox.innerHTML = lines;

    if (d.ok) {
      logBox.innerHTML += '<div class="l-ok" style="margin-top:8px;font-weight:700">✅ Migration สำเร็จทุกขั้นตอน</div>';
      setTimeout(() => location.reload(), 1200);
    } else {
      logBox.innerHTML += '<div class="l-err" style="margin-top:8px">❌ เกิดข้อผิดพลาด — ตรวจสอบข้อความข้างบน</div>';
      btn.disabled = false;
      txt.textContent = '🔄 ลองใหม่';
    }
  } catch (e) {
    logBox.innerHTML += `<div class="l-err">✗ ${esc(e.message)}</div>`;
    btn.disabled = false;
    txt.textContent = '🔄 ลองใหม่';
  }
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
