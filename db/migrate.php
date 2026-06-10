<?php
/**
 * migrate.php — รัน personnel.sql และตั้งรหัสผ่านใหม่
 * ใช้ครั้งเดียว แล้วลบทิ้ง
 */
require_once __DIR__ . '/../config/db.php';

// ป้องกันการเรียกโดยไม่ตั้งใจ
if (($_GET['confirm'] ?? '') !== 'run') {
    echo '<h2>Migration</h2>';
    echo '<p>เพิ่ม/อัปเดตบัญชีบุคลากรจาก personnel.sql และตั้งรหัสผ่านใหม่</p>';
    echo '<p><b>URL:</b> <code>migrate.php?confirm=run&pass=รหัสผ่านใหม่</code></p>';
    echo '<p>ตัวอย่าง: <a href="?confirm=run&pass=password">migrate.php?confirm=run&pass=password</a></p>';
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
