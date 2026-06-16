<?php
require_once __DIR__ . '/_common.php';
$actor = require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

$avatarDir    = __DIR__ . '/../uploads/avatars';
$avatarWebDir = 'uploads/avatars';

// สร้างโฟลเดอร์เก็บภาพ + .htaccess ให้เองถ้ายังไม่มี (กันพังตอน deploy บน server จริง)
$mkdirError = null;
if (!is_dir($avatarDir)) {
    if (!@mkdir($avatarDir, 0755, true) && !is_dir($avatarDir)) {
        $e = error_get_last();
        $mkdirError = $e['message'] ?? 'ไม่ทราบสาเหตุ';
    }
}
// ถ้ามีโฟลเดอร์อยู่แล้วแต่เขียนไม่ได้ (เจอบ่อยตอน deploy ด้วย user คนละคนกับ web server) ลองคืนสิทธิ์ให้
if (is_dir($avatarDir) && !is_writable($avatarDir)) {
    @chmod($avatarDir, 0775);
}
$htaccess = $avatarDir . '/.htaccess';
if (is_dir($avatarDir) && !file_exists($htaccess)) {
    @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all granted\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Allow from all\n</IfModule>\n<FilesMatch \"\\.(php|php\\d|phtml|pl|py|cgi|sh)$\">\n    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n    <IfModule !mod_authz_core.c>\n        Deny from all\n    </IfModule>\n</FilesMatch>\n");
}

function deleteOldAvatar(string $path = null): void {
    if (!$path) return;
    $full = __DIR__ . '/../' . $path;
    // ลบเฉพาะไฟล์ในโฟลเดอร์ uploads/avatars เท่านั้น ป้องกัน path traversal
    if (str_starts_with(realpath(dirname($full)) ?: '', realpath(__DIR__ . '/../uploads/avatars') ?: "\0")
        && is_file($full)) {
        @unlink($full);
    }
}

// POST /api/avatar.php — อัปโหลด/เปลี่ยนภาพประจำตัว
if ($method === 'POST') {
    if (!is_dir($avatarDir)) {
        err('ไม่สามารถสร้างโฟลเดอร์ uploads/avatars ได้' . ($mkdirError ? " ({$mkdirError})" : '') .
            ' — รันคำสั่งบน server: mkdir -p uploads/avatars && chmod 775 uploads/avatars', 500);
    }
    if (!is_writable($avatarDir)) {
        err('โฟลเดอร์ uploads/avatars มีอยู่แล้วแต่เขียนไม่ได้ — รันคำสั่งบน server: chmod 775 uploads/avatars', 500);
    }
    if (empty($_FILES['avatar'])) err('ไม่พบไฟล์ภาพ');

    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK) err('อัปโหลดไฟล์ล้มเหลว');

    $maxSize = 2 * 1024 * 1024; // 2 MB
    if ($file['size'] > $maxSize) err('ไฟล์ภาพขนาดใหญ่เกิน 2 MB');

    // ตรวจว่าเป็นไฟล์ภาพจริง ไม่ใช่ไฟล์ปลอมแปลงนามสกุล (ใช้ getimagesize เป็นหลัก ไม่พึ่ง fileinfo ที่อาจไม่เปิดบน server)
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false) err('ไฟล์ภาพไม่ถูกต้อง');

    $extByMime = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!isset($extByMime[$imgInfo[2]])) err('รองรับเฉพาะไฟล์ภาพ JPG, PNG, WEBP');

    $cur = $db->prepare('SELECT avatar_path FROM users WHERE id = ?');
    $cur->execute([$actor['id']]);
    $oldPath = $cur->fetchColumn();

    $storedName = bin2hex(random_bytes(16)) . '.' . $extByMime[$imgInfo[2]];
    if (!move_uploaded_file($file['tmp_name'], $avatarDir . '/' . $storedName)) {
        err('บันทึกไฟล์ล้มเหลว', 500);
    }

    $webPath = $avatarWebDir . '/' . $storedName;
    $db->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')->execute([$webPath, $actor['id']]);
    deleteOldAvatar($oldPath ?: null);

    audit('avatar_update', (string)$actor['id']);

    $row = $db->prepare('SELECT id, username, display_name, role, init, avatar_path FROM users WHERE id = ?');
    $row->execute([$actor['id']]);
    json_out($row->fetch());
}

// DELETE /api/avatar.php — ลบภาพประจำตัว (กลับไปใช้ตัวย่อ)
if ($method === 'DELETE') {
    $cur = $db->prepare('SELECT avatar_path FROM users WHERE id = ?');
    $cur->execute([$actor['id']]);
    $oldPath = $cur->fetchColumn();

    $db->prepare('UPDATE users SET avatar_path = NULL WHERE id = ?')->execute([$actor['id']]);
    deleteOldAvatar($oldPath ?: null);

    audit('avatar_remove', (string)$actor['id']);

    $row = $db->prepare('SELECT id, username, display_name, role, init, avatar_path FROM users WHERE id = ?');
    $row->execute([$actor['id']]);
    json_out($row->fetch());
}

err('Method not allowed', 405);
