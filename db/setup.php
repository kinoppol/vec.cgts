<?php
/**
 * ตั้งค่าฐานข้อมูลครั้งแรก
 * เรียกใช้: http://localhost/vec.cgts/db/setup.php
 * ลบไฟล์นี้หลังติดตั้งเสร็จแล้ว!
 */

date_default_timezone_set('Asia/Bangkok');

// ป้องกันการเรียกซ้ำโดยไม่ตั้งใจ
$token = $_GET['confirm'] ?? '';
if ($token !== 'install') {
    echo "<pre>เรียกใช้: setup.php?confirm=install\n\nคำเตือน: ไฟล์นี้จะสร้างฐานข้อมูลใหม่ทั้งหมด กรุณาสำรองข้อมูลก่อน</pre>";
    exit;
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "<pre>";

    // สร้าง DB + ตาราง
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    foreach (explode(';', $schema) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        echo "✓ " . substr($stmt, 0, 60) . "…\n";
    }

    // เพิ่มข้อมูลตัวอย่าง
    $seed = file_get_contents(__DIR__ . '/seed.sql');
    foreach (explode(';', $seed) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        echo "✓ (seed) " . substr($stmt, 0, 60) . "…\n";
    }

    // สร้าง password hash สำหรับ "password"
    $hash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->exec("USE vec_cgts");
    $pdo->prepare("UPDATE users SET password_hash = ?")->execute([$hash]);
    echo "\n✓ อัปเดต password hash สำเร็จ\n";

    echo "\n\n=== ติดตั้งเสร็จสมบูรณ์ ===\n";
    echo "บัญชีทดสอบ:\n";
    echo "  officer   / password\n";
    echo "  dir_legal / password\n";
    echo "  dir_admin / password\n";
    echo "\nURL: http://localhost/vec.cgts/\n";
    echo "\n⚠️  กรุณาลบไฟล์ setup.php นี้หลังติดตั้ง\n";
    echo "</pre>";

} catch (PDOException $e) {
    echo "<pre style='color:red'>❌ " . htmlspecialchars($e->getMessage()) . "</pre>";
}
