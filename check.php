<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html><html><head><meta charset="utf-8">
<style>body{font-family:monospace;padding:24px;background:#111;color:#eee}
.ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}
table{border-collapse:collapse;margin:12px 0}
td,th{border:1px solid #444;padding:6px 14px;text-align:left}th{background:#222}
h2{color:#fbbf24;margin:24px 0 8px}pre{background:#1a1a1a;padding:12px;border-radius:6px}</style>
</head><body>
<h1>🔍 System Check</h1>

<h2>1. PHP</h2>
<?php echo '<span class="ok">✓ PHP ' . PHP_VERSION . '</span><br>'; ?>
SAPI: <?= PHP_SAPI ?><br>
HTTPS: <?= ($_SERVER['HTTPS'] ?? 'not set') ?><br>
PHP_SELF: <?= $_SERVER['PHP_SELF'] ?><br>
APP_BASE would be: <b><?= rtrim(dirname($_SERVER['PHP_SELF']), '/\\') ?></b><br>

<h2>2. Database Connection</h2>
<?php
try {
    $db = getDB();
    $ver = $db->query('SELECT VERSION()')->fetchColumn();
    echo '<span class="ok">✓ Connected — ' . htmlspecialchars($ver) . '</span><br>';

    echo '<h2>3. Users in Database</h2>';
    $users = $db->query('SELECT username, display_name, role, active, LENGTH(password_hash) AS hash_len FROM users ORDER BY role, username')->fetchAll();
    echo '<table><tr><th>username</th><th>display_name</th><th>role</th><th>active</th><th>hash_len</th></tr>';
    foreach ($users as $u) {
        $ac = $u['active'] ? '<span class="ok">✓</span>' : '<span class="err">✗</span>';
        $hl = $u['hash_len'] > 50 ? '<span class="ok">' . $u['hash_len'] . '</span>' : '<span class="err">' . $u['hash_len'] . '</span>';
        echo "<tr><td><b>{$u['username']}</b></td><td>{$u['display_name']}</td><td>{$u['role']}</td><td>{$ac}</td><td>{$hl}</td></tr>";
    }
    echo '</table>';

    echo '<h2>4. Test password_verify</h2>';
    if (isset($_GET['user'], $_GET['pass'])) {
        $u = $_GET['user'];
        $p = $_GET['pass'];
        $row = $db->prepare('SELECT password_hash FROM users WHERE username = ?');
        $row->execute([$u]);
        $r = $row->fetch();
        if (!$r) {
            echo '<span class="err">✗ ไม่พบ username: ' . htmlspecialchars($u) . '</span>';
        } elseif (password_verify($p, $r['password_hash'])) {
            echo '<span class="ok">✓ รหัสผ่านถูกต้องสำหรับ ' . htmlspecialchars($u) . '</span>';
        } else {
            echo '<span class="err">✗ รหัสผ่านไม่ตรง สำหรับ ' . htmlspecialchars($u) . '</span>';
            echo '<br><span class="warn">Hash prefix: ' . substr($r['password_hash'], 0, 7) . '...</span>';
        }
    } else {
        echo 'ทดสอบ: <a href="?user=dir_admin&pass=password" style="color:#60a5fa">?user=dir_admin&pass=password</a><br>';
        echo 'หรือระบุเอง: <code>check.php?user=USERNAME&pass=PASSWORD</code>';
    }

} catch (Throwable $e) {
    echo '<span class="err">✗ ' . htmlspecialchars($e->getMessage()) . '</span>';
}
?>

<h2>5. Session Test</h2>
<?php
session_name('vec_cgts_sess');
session_start();
$_SESSION['test'] = time();
echo '<span class="ok">✓ Session ID: ' . session_id() . '</span><br>';
echo 'session_save_path: ' . session_save_path() . '<br>';
?>

<p style="color:#f87171;margin-top:32px"><b>⚠ ลบไฟล์นี้ทันทีหลังตรวจสอบ: <code>check.php</code></b></p>
</body></html>
