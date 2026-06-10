<?php
/**
 * install.php — ตัวช่วยติดตั้งระบบรับเรื่องร้องเรียน–ร้องทุกข์ สอศ.
 * เรียกใช้ครั้งเดียว แล้วลบหรือ block ไฟล์นี้
 */

session_start();

define('INSTALL_LOCK', __DIR__ . '/install.lock');
define('CONFIG_PATH', __DIR__ . '/config/db.php');
define('SCHEMA_PATH', __DIR__ . '/db/schema.sql');
define('SEED_PATH',   __DIR__ . '/db/seed.sql');

/* --------------------------------------------------------
   AJAX handlers — ต้องอยู่ก่อน guard เสมอ
-------------------------------------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'test_db') {
    header('Content-Type: application/json');
    echo json_encode(testConnection(
        $_POST['host'] ?? 'localhost',
        $_POST['port'] ?? '3306',
        $_POST['user'] ?? 'root',
        $_POST['pass'] ?? '',
        $_POST['dbname'] ?? 'vec_cgts'
    ));
    exit;
}

if ($action === 'install') {
    header('Content-Type: application/json');
    echo json_encode(runInstall(
        $_POST['host']   ?? 'localhost',
        $_POST['port']   ?? '3306',
        $_POST['user']   ?? 'root',
        $_POST['pass']   ?? '',
        $_POST['dbname'] ?? 'vec_cgts',
        $_POST['app_pass'] ?? 'password'
    ));
    exit;
}

/* --------------------------------------------------------
   Guard: ถ้าติดตั้งแล้วและไม่ได้ force reset
-------------------------------------------------------- */
if (file_exists(INSTALL_LOCK) && ($_GET['reset'] ?? '') !== '1') {
    $info = json_decode(file_get_contents(INSTALL_LOCK), true);
    showLocked($info);
    exit;
}

/* --------------------------------------------------------
   Core functions
-------------------------------------------------------- */
function testConnection(string $host, string $port, string $user, string $pass, string $dbname): array {
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        return ['ok' => true, 'version' => $ver, 'msg' => "เชื่อมต่อสำเร็จ — MySQL/MariaDB {$ver}"];
    } catch (PDOException $e) {
        return ['ok' => false, 'msg' => $e->getMessage()];
    }
}

function runInstall(string $host, string $port, string $user, string $pass, string $dbname, string $appPass): array {
    $log = [];
    try {
        /* 1. เชื่อมต่อโดยไม่ระบุ DB */
        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $log[] = ['ok' => true, 'msg' => "เชื่อมต่อ {$host}:{$port} สำเร็จ"];

        /* 2. สร้าง/ใช้ฐานข้อมูล */
        $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbname);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$safeDb}`");
        $log[] = ['ok' => true, 'msg' => "ฐานข้อมูล `{$safeDb}` พร้อมใช้งาน"];

        /* 3. รัน schema.sql */
        $schema = file_get_contents(SCHEMA_PATH);
        // แยก statement และข้ามบรรทัด comment + USE
        $stmts = splitSQL($schema);
        foreach ($stmts as $stmt) {
            if (preg_match('/^\s*USE\s+/i', $stmt)) continue; // ข้ามคำสั่ง USE ใน schema
            $pdo->exec($stmt);
        }
        $log[] = ['ok' => true, 'msg' => 'สร้างตารางทั้งหมดสำเร็จ'];

        /* 4. รัน seed.sql */
        $seed = file_get_contents(SEED_PATH);
        $stmts = splitSQL($seed);
        foreach ($stmts as $stmt) {
            if (preg_match('/^\s*USE\s+/i', $stmt)) continue;
            $pdo->exec($stmt);
        }
        $log[] = ['ok' => true, 'msg' => 'เพิ่มข้อมูลตัวอย่างสำเร็จ'];

        /* 5. รัน personnel.sql — โครงสร้างบุคลากรจริง */
        $personnelPath = __DIR__ . '/db/personnel.sql';
        if (file_exists($personnelPath)) {
            $personnel = file_get_contents($personnelPath);
            $stmts = splitSQL($personnel);
            foreach ($stmts as $stmt) {
                if (preg_match('/^\s*USE\s+/i', $stmt)) continue;
                // SET @var ต้องใช้ exec ปกติ
                $pdo->exec($stmt);
            }
            $log[] = ['ok' => true, 'msg' => 'เพิ่มข้อมูลบุคลากร (personnel.sql) สำเร็จ'];
        }

        /* 7. อัปเดต password hash ทุก account */
        $hash = password_hash($appPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password_hash = ?")->execute([$hash]);
        $log[] = ['ok' => true, 'msg' => 'ตั้งรหัสผ่านทุกบัญชีสำเร็จ'];

        /* 8. เขียน config/db.php */
        writeConfig($host, $port, $user, $pass, $safeDb);
        $log[] = ['ok' => true, 'msg' => 'บันทึก config/db.php สำเร็จ'];

        /* 9. เขียน install.lock */
        file_put_contents(INSTALL_LOCK, json_encode([
            'installed_at' => date('c'),
            'host'   => $host,
            'dbname' => $safeDb,
            'user'   => $user,
        ]));

        return ['ok' => true, 'log' => $log, 'app_pass' => $appPass];

    } catch (PDOException $e) {
        $log[] = ['ok' => false, 'msg' => $e->getMessage()];
        return ['ok' => false, 'log' => $log];
    } catch (Throwable $e) {
        $log[] = ['ok' => false, 'msg' => $e->getMessage()];
        return ['ok' => false, 'log' => $log];
    }
}

function splitSQL(string $sql): array {
    // ลบ comments แบบ -- และ /* */
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    $stmts = [];
    foreach (explode(';', $sql) as $s) {
        $s = trim($s);
        if ($s !== '') $stmts[] = $s;
    }
    return $stmts;
}

function writeConfig(string $host, string $port, string $user, string $pass, string $dbname): void {
    $safePass = addslashes($pass);
    $safeUser = addslashes($user);
    $now = date('Y-m-d H:i:s');
    $content = <<<PHP
<?php
// สร้างโดย install.php เมื่อ {$now} — ห้ามแก้ไขโดยตรง
define('DB_HOST',    '{$host}');
define('DB_PORT',    '{$port}');
define('DB_NAME',    '{$dbname}');
define('DB_USER',    '{$safeUser}');
define('DB_PASS',    '{$safePass}');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return \$pdo;
}
PHP;
    file_put_contents(CONFIG_PATH, $content);
}

/* --------------------------------------------------------
   Locked screen
-------------------------------------------------------- */
function showLocked(array $info): void { ?>
<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ติดตั้งแล้ว — สอศ.</title>
<?php headStyles() ?>
</head><body class="center-page">
<div class="card locked-card">
  <div class="lock-icon">🔒</div>
  <h2>ระบบติดตั้งแล้ว</h2>
  <p class="muted">ติดตั้งเมื่อ <?= htmlspecialchars($info['installed_at'] ?? '') ?><br>
     ฐานข้อมูล <b><?= htmlspecialchars($info['dbname'] ?? '') ?></b>
     ที่ <b><?= htmlspecialchars($info['host'] ?? '') ?></b></p>
  <div class="notice warn">
    <b>คำเตือน:</b> ควรลบหรือ block ไฟล์ <code>install.php</code> เพื่อความปลอดภัย
  </div>
  <div class="btn-row">
    <a href="index.php" class="btn btn-primary">เข้าสู่ระบบ</a>
    <a href="install.php?reset=1" class="btn btn-ghost">ติดตั้งใหม่</a>
  </div>
</div>
</body></html>
<?php }

/* --------------------------------------------------------
   Shared head styles
-------------------------------------------------------- */
function headStyles(): void { ?>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --maroon: #7A1E2A; --maroon-d: #5c1620; --gold: #C9A84C;
    --bg: #f5f4f2; --surface: #fff; --surface-2: #f9f8f6;
    --ink: #1a1a1a; --ink-2: #444; --ink-3: #888;
    --line: #e5e2dd; --radius: 12px;
    --ok: #1e7e34; --ok-bg: #d4edda;
    --err: #a31515; --err-bg: #fde8e8;
    --warn: #856404; --warn-bg: #fff3cd;
    --info: #0c5460; --info-bg: #d1ecf1;
    --fs: 'Noto Sans Thai', 'Sarabun', system-ui, sans-serif;
    --shadow: 0 4px 24px rgba(0,0,0,.08);
  }
  body { font-family: var(--fs); background: var(--bg); color: var(--ink); font-size: 15px; line-height: 1.6; }
  body.center-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }

  /* layout */
  .installer { max-width: 760px; width: 100%; margin: 40px auto; padding: 0 16px 60px; }
  .card { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); padding: 32px; }
  .locked-card { max-width: 440px; text-align: center; }
  .lock-icon { font-size: 48px; margin-bottom: 12px; }
  .locked-card h2 { font-size: 22px; margin-bottom: 8px; }

  /* header */
  .inst-header { text-align: center; margin-bottom: 36px; }
  .inst-header .logo { width: 64px; height: 64px; margin: 0 auto 14px; }
  .inst-header h1 { font-size: 22px; font-weight: 700; color: var(--maroon); }
  .inst-header p { color: var(--ink-3); font-size: 14px; margin-top: 4px; }

  /* stepper */
  .stepper { display: flex; align-items: center; gap: 0; margin-bottom: 32px; }
  .step { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; }
  .step:not(:last-child)::after {
    content: ''; position: absolute; top: 18px; left: calc(50% + 18px);
    right: calc(-50% + 18px); height: 2px; background: var(--line); z-index: 0;
  }
  .step.done:not(:last-child)::after { background: var(--maroon); }
  .step-circle {
    width: 36px; height: 36px; border-radius: 50%; border: 2px solid var(--line);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: var(--ink-3); background: var(--surface);
    position: relative; z-index: 1; transition: all .25s;
  }
  .step.active .step-circle { border-color: var(--maroon); color: var(--maroon); background: #f9f0f1; }
  .step.done .step-circle { border-color: var(--maroon); background: var(--maroon); color: #fff; }
  .step-label { font-size: 12px; color: var(--ink-3); margin-top: 6px; text-align: center; }
  .step.active .step-label { color: var(--maroon); font-weight: 600; }
  .step.done .step-label { color: var(--maroon); }

  /* form */
  .section-title { font-size: 16px; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid var(--maroon); color: var(--maroon); }
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .form-full { grid-column: 1 / -1; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .field label { font-size: 13px; font-weight: 600; color: var(--ink-2); }
  .field label .req { color: var(--err); margin-left: 2px; }
  .field input, .field select {
    padding: 10px 14px; border: 1.5px solid var(--line); border-radius: 8px;
    font-family: var(--fs); font-size: 14px; color: var(--ink);
    background: var(--surface); transition: border-color .15s;
    outline: none;
  }
  .field input:focus, .field select:focus { border-color: var(--maroon); }
  .field .hint { font-size: 12px; color: var(--ink-3); }
  .input-row { display: flex; gap: 8px; }
  .input-row input[name="port"] { width: 90px; flex: none; }
  .input-row input[name="host"] { flex: 1; }

  /* test connection */
  .conn-test { display: flex; gap: 10px; align-items: flex-start; margin-top: 6px; }
  #testResult {
    flex: 1; padding: 10px 14px; border-radius: 8px; font-size: 13px;
    display: none; border: 1.5px solid transparent;
  }
  #testResult.ok  { display: block; background: var(--ok-bg);  border-color: #b8dfc2; color: var(--ok); }
  #testResult.err { display: block; background: var(--err-bg); border-color: #f5c6c6; color: var(--err); }

  /* notices */
  .notice { padding: 12px 16px; border-radius: 8px; font-size: 13.5px; margin-top: 16px; line-height: 1.55; }
  .notice.warn { background: var(--warn-bg); color: var(--warn); border: 1px solid #ffd97a; }
  .notice.info { background: var(--info-bg); color: var(--info); border: 1px solid #b8d8df; }
  .notice.ok   { background: var(--ok-bg);  color: var(--ok);   border: 1px solid #b8dfc2; }
  .muted { color: var(--ink-3); font-size: 14px; margin-bottom: 16px; }

  /* progress log */
  #logBox {
    background: #1a1a1a; color: #d4d4d4; border-radius: 8px; padding: 16px 18px;
    font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; line-height: 1.8;
    min-height: 120px; margin-top: 16px; display: none;
  }
  #logBox.visible { display: block; }
  .log-ok  { color: #6ac47b; }
  .log-err { color: #f47979; }
  .log-spin { color: #c9a84c; }

  /* accounts table */
  .accounts { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 14px; }
  .accounts th { text-align: left; padding: 8px 12px; background: var(--surface-2); font-size: 12px; color: var(--ink-3); border-bottom: 1px solid var(--line); }
  .accounts td { padding: 10px 12px; border-bottom: 1px solid var(--line); }
  .accounts tr:last-child td { border-bottom: none; }
  .badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
  .badge-officer  { background: #e9f0fb; color: #2c5282; }
  .badge-director { background: #fff0e0; color: #7b4510; }
  .badge-admin    { background: #f0e9fb; color: #5a2c82; }

  /* success */
  .success-icon { font-size: 56px; text-align: center; margin-bottom: 12px; }
  .success-title { font-size: 20px; font-weight: 700; color: var(--ok); text-align: center; margin-bottom: 6px; }
  .success-sub { text-align: center; color: var(--ink-3); font-size: 14px; margin-bottom: 24px; }

  /* buttons */
  .btn-row { display: flex; gap: 12px; margin-top: 24px; }
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    padding: 11px 24px; border-radius: 8px; font-family: var(--fs); font-size: 14px;
    font-weight: 600; cursor: pointer; border: none; transition: all .15s; text-decoration: none;
    white-space: nowrap;
  }
  .btn:disabled { opacity: .55; cursor: not-allowed; }
  .btn-primary { background: var(--maroon); color: #fff; }
  .btn-primary:hover:not(:disabled) { background: var(--maroon-d); }
  .btn-outline { background: transparent; color: var(--maroon); border: 1.5px solid var(--maroon); }
  .btn-outline:hover:not(:disabled) { background: #f9f0f1; }
  .btn-ghost { background: transparent; color: var(--ink-3); border: 1.5px solid var(--line); }
  .btn-ghost:hover { background: var(--surface-2); }
  .btn-sm { padding: 8px 16px; font-size: 13px; }
  .ml-auto { margin-left: auto; }
  code { background: #f0ede9; padding: 1px 6px; border-radius: 4px; font-size: 13px; font-family: monospace; }

  /* divider */
  .divider { border: none; border-top: 1px solid var(--line); margin: 24px 0; }

  /* spinner */
  @keyframes spin { to { transform: rotate(360deg); } }
  .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }

  /* responsive */
  @media (max-width: 540px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-full { grid-column: 1; }
    .btn-row { flex-direction: column; }
    .step-label { display: none; }
  }
</style>
<?php }

/* --------------------------------------------------------
   Main HTML
-------------------------------------------------------- */
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ติดตั้งระบบ — สอศ. ระบบรับเรื่องร้องเรียน</title>
<?php headStyles() ?>
</head>
<body>

<div class="installer">

  <!-- Header -->
  <div class="inst-header">
    <svg class="logo" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="32" cy="32" r="30" fill="#7A1E2A" opacity=".12"/>
      <circle cx="32" cy="32" r="22" fill="#7A1E2A" opacity=".18"/>
      <path d="M32 10 L38 26 L54 26 L41 36 L46 52 L32 43 L18 52 L23 36 L10 26 L26 26 Z" fill="#7A1E2A" opacity=".7"/>
    </svg>
    <h1>ติดตั้งระบบรับเรื่องร้องเรียน–ร้องทุกข์</h1>
    <p>สำนักงานคณะกรรมการการอาชีวศึกษา (สอศ.)</p>
  </div>

  <!-- Stepper -->
  <div class="stepper" id="stepper">
    <div class="step active" id="st1">
      <div class="step-circle">1</div>
      <div class="step-label">ตั้งค่าฐานข้อมูล</div>
    </div>
    <div class="step" id="st2">
      <div class="step-circle">2</div>
      <div class="step-label">ติดตั้งระบบ</div>
    </div>
    <div class="step" id="st3">
      <div class="step-circle">3</div>
      <div class="step-label">เสร็จสิ้น</div>
    </div>
  </div>

  <!-- Step 1: Database Config -->
  <div id="page1" class="card">
    <div class="section-title">🗄️ ตั้งค่าการเชื่อมต่อฐานข้อมูล</div>

    <div class="form-grid">
      <div class="field form-full">
        <label>เซิร์ฟเวอร์ฐานข้อมูล <span class="req">*</span></label>
        <div class="input-row">
          <input type="text" name="host" id="host" value="localhost" placeholder="localhost หรือ IP" autocomplete="off">
          <input type="number" name="port" id="port" value="3306" placeholder="พอร์ต" min="1" max="65535">
        </div>
        <span class="hint">โดยทั่วไปใช้ localhost และพอร์ต 3306</span>
      </div>

      <div class="field">
        <label>ชื่อผู้ใช้ฐานข้อมูล <span class="req">*</span></label>
        <input type="text" name="dbuser" id="dbuser" value="root" autocomplete="off">
      </div>
      <div class="field">
        <label>รหัสผ่านฐานข้อมูล</label>
        <input type="password" name="dbpass" id="dbpass" placeholder="(เว้นว่างหากไม่มี)" autocomplete="new-password">
      </div>

      <div class="field form-full">
        <label>ชื่อฐานข้อมูล <span class="req">*</span></label>
        <input type="text" name="dbname" id="dbname" value="vec_cgts" autocomplete="off">
        <span class="hint">หากยังไม่มีฐานข้อมูลนี้ ระบบจะสร้างให้อัตโนมัติ</span>
      </div>
    </div>

    <div class="notice info">
      <b>ต้องการสิทธิ์:</b> CREATE DATABASE, CREATE TABLE, INSERT, UPDATE, DELETE บนฐานข้อมูลที่ระบุ
    </div>

    <hr class="divider">

    <div class="conn-test">
      <button class="btn btn-outline btn-sm" id="btnTest" onclick="testConn()">
        <span id="testTxt">🔌 ทดสอบการเชื่อมต่อ</span>
      </button>
      <div id="testResult"></div>
    </div>

    <div class="btn-row">
      <button class="btn btn-primary ml-auto" id="btnNext1" onclick="goStep2()" disabled>
        ถัดไป →
      </button>
    </div>
  </div>

  <!-- Step 2: App Config + Install -->
  <div id="page2" class="card" style="display:none">
    <div class="section-title">⚙️ ตั้งค่าระบบและบัญชีผู้ใช้</div>

    <div class="form-grid">
      <div class="field form-full">
        <label>รหัสผ่านบัญชีทดสอบ <span class="req">*</span></label>
        <input type="password" id="appPass" value="password" autocomplete="new-password">
        <span class="hint">บัญชีทดสอบทั้ง 3 บัญชีจะใช้รหัสผ่านเดียวกัน</span>
      </div>
    </div>

    <div class="notice warn">
      ⚠️ <b>คำเตือน:</b> การติดตั้งจะ <b>สร้างฐานข้อมูลใหม่</b> และเพิ่มข้อมูลตัวอย่าง
      หากมีฐานข้อมูลเดิมอยู่แล้ว ข้อมูลจะถูกแทนที่
    </div>

    <div id="logBox"></div>

    <div class="btn-row">
      <button class="btn btn-ghost" onclick="backStep1()">← ย้อนกลับ</button>
      <button class="btn btn-primary ml-auto" id="btnInstall" onclick="runInstall()">
        <span id="installTxt">🚀 ติดตั้งระบบ</span>
      </button>
    </div>
  </div>

  <!-- Step 3: Success -->
  <div id="page3" class="card" style="display:none">
    <div class="success-icon">✅</div>
    <div class="success-title">ติดตั้งสำเร็จ!</div>
    <div class="success-sub">ระบบรับเรื่องร้องเรียน–ร้องทุกข์ พร้อมใช้งาน</div>

    <div class="section-title" style="margin-top:8px">👤 บัญชีผู้ใช้งานระบบ</div>
    <table class="accounts">
      <thead>
        <tr><th>ชื่อผู้ใช้</th><th>รหัสผ่าน</th><th>ชื่อ-นามสกุล</th><th>บทบาท / กลุ่มงาน</th></tr>
      </thead>
      <tbody>
        <tr>
          <td><code>dir_admin</code></td>
          <td><code id="passShow1">—</code></td>
          <td>ผู้อำนวยการสำนักอำนวยการ</td>
          <td><span class="badge badge-admin">ผอ.สำนักอำนวยการ</span></td>
        </tr>
        <tr>
          <td><code>wornwut</code></td>
          <td><code id="passShow2">—</code></td>
          <td>นายวรวุฒิ ดำขา</td>
          <td><span class="badge badge-director">ผอ.กลุ่มนิติการ</span></td>
        </tr>
        <tr>
          <td><code>yawrata</code></td>
          <td><code id="passShow3">—</code></td>
          <td>นางสาวเยาวริดา พิณสายแก้ว</td>
          <td><span class="badge badge-officer">นิติกร · กฎหมายและระเบียบ</span></td>
        </tr>
        <tr>
          <td><code>nawan</code></td>
          <td><code id="passShow4">—</code></td>
          <td>นายณวณ เจริญหลาย</td>
          <td><span class="badge badge-officer">นิติกร · กฎหมายและระเบียบ</span></td>
        </tr>
        <tr>
          <td><code>siwakorn</code></td>
          <td><code id="passShow5">—</code></td>
          <td>นายศิวกร เพชรสีเงิน</td>
          <td><span class="badge badge-officer">นิติกร · กฎหมายและระเบียบ</span></td>
        </tr>
        <tr>
          <td><code>panisa</code></td>
          <td><code id="passShow6">—</code></td>
          <td>นางสาวภานิชา จันทราทิพย์</td>
          <td><span class="badge badge-officer">นิติกร · วินัย</span></td>
        </tr>
        <tr>
          <td><code>jidapa</code></td>
          <td><code id="passShow7">—</code></td>
          <td>นางสาวจิดาภา ทองศรีสังข์</td>
          <td><span class="badge badge-officer">พนง.บริหาร · วินัย</span></td>
        </tr>
        <tr>
          <td><code>kanjana</code></td>
          <td><code id="passShow8">—</code></td>
          <td>นางสาวกาญจนา อนันต์โก</td>
          <td><span class="badge badge-officer">พนง.บริหาร · วินัย</span></td>
        </tr>
        <tr>
          <td><code>chotika</code></td>
          <td><code id="passShow9">—</code></td>
          <td>นางสาวโชติกา วิริยะจีระพิพัฒน์</td>
          <td><span class="badge badge-officer">พนง.บริหาร · วินัย</span></td>
        </tr>
      </tbody>
    </table>

    <div class="notice warn" style="margin-top:20px">
      🔒 <b>เพื่อความปลอดภัย:</b> โปรดลบหรือ block ไฟล์ <code>install.php</code>
      หลังจากติดตั้งเสร็จสิ้น
    </div>

    <div class="btn-row">
      <a href="index.php" class="btn btn-primary ml-auto">เข้าสู่ระบบ →</a>
    </div>
  </div>

</div><!-- /.installer -->

<script>
let connOk = false;

/* ---------- Step navigation ---------- */
function setStep(n) {
  for (let i = 1; i <= 3; i++) {
    const st = document.getElementById('st' + i);
    st.className = 'step' + (i < n ? ' done' : i === n ? ' active' : '');
    if (i < n) st.querySelector('.step-circle').textContent = '✓';
  }
  document.getElementById('page1').style.display = n === 1 ? '' : 'none';
  document.getElementById('page2').style.display = n === 2 ? '' : 'none';
  document.getElementById('page3').style.display = n === 3 ? '' : 'none';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goStep2() { if (connOk) setStep(2); }
function backStep1() { setStep(1); }

/* ---------- Test connection ---------- */
async function testConn() {
  const btn = document.getElementById('btnTest');
  const txt = document.getElementById('testTxt');
  const res = document.getElementById('testResult');
  btn.disabled = true;
  txt.innerHTML = '<span class="spinner" style="border-color:rgba(122,30,42,.3);border-top-color:var(--maroon)"></span> กำลังทดสอบ…';
  res.className = '';
  res.style.display = 'none';

  try {
    const r = await fetch('install.php', {
      method: 'POST',
      body: new URLSearchParams({
        action: 'test_db',
        host:   document.getElementById('host').value,
        port:   document.getElementById('port').value,
        user:   document.getElementById('dbuser').value,
        pass:   document.getElementById('dbpass').value,
        dbname: document.getElementById('dbname').value,
      })
    });
    const d = await r.json();
    res.className = d.ok ? 'ok' : 'err';
    res.textContent = d.ok ? '✓ ' + d.msg : '✗ ' + d.msg;
    res.style.display = '';
    connOk = d.ok;
    document.getElementById('btnNext1').disabled = !d.ok;
  } catch (e) {
    res.className = 'err';
    res.textContent = '✗ ไม่สามารถเชื่อมต่อได้: ' + e.message;
    res.style.display = '';
    connOk = false;
  }
  btn.disabled = false;
  txt.innerHTML = '🔌 ทดสอบการเชื่อมต่อ';
}

/* ---------- Run install ---------- */
async function runInstall() {
  const btn    = document.getElementById('btnInstall');
  const txt    = document.getElementById('installTxt');
  const logBox = document.getElementById('logBox');

  btn.disabled = true;
  txt.innerHTML = '<span class="spinner"></span> กำลังติดตั้ง…';
  logBox.className = 'visible';
  logBox.innerHTML = '<span class="log-spin">⟳ เริ่มต้นการติดตั้ง…</span>';

  const appPass = document.getElementById('appPass').value || 'password';

  try {
    const r = await fetch('install.php', {
      method: 'POST',
      body: new URLSearchParams({
        action:   'install',
        host:     document.getElementById('host').value,
        port:     document.getElementById('port').value,
        user:     document.getElementById('dbuser').value,
        pass:     document.getElementById('dbpass').value,
        dbname:   document.getElementById('dbname').value,
        app_pass: appPass,
      })
    });
    const d = await r.json();

    // render log
    const lines = (d.log || []).map(l =>
      `<div class="${l.ok ? 'log-ok' : 'log-err'}">${l.ok ? '✓' : '✗'} ${escHtml(l.msg)}</div>`
    ).join('');
    logBox.innerHTML = lines;

    if (d.ok) {
      // set password display
      ['passShow1','passShow2','passShow3','passShow4','passShow5','passShow6','passShow7','passShow8','passShow9'].forEach(id =>
        document.getElementById(id).textContent = appPass
      );
      setTimeout(() => setStep(3), 600);
    } else {
      logBox.innerHTML += '<div class="log-err" style="margin-top:8px">❌ การติดตั้งล้มเหลว — ตรวจสอบข้อความข้างบน</div>';
      btn.disabled = false;
      txt.textContent = '🔄 ลองใหม่';
    }
  } catch (e) {
    logBox.innerHTML += `<div class="log-err">✗ ${escHtml(e.message)}</div>`;
    btn.disabled = false;
    txt.textContent = '🔄 ลองใหม่';
  }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* allow Enter in form fields to trigger test */
document.addEventListener('keydown', e => {
  if (e.key === 'Enter' && document.getElementById('page1').style.display !== 'none') {
    testConn();
  }
});
</script>
</body>
</html>
