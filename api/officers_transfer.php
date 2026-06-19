<?php
/**
 * officers_transfer.php — ส่งออก / นำเข้าข้อมูลบุคลากร (ZIP)
 *
 * GET  /api/officers_transfer.php          → ดาวน์โหลด ZIP
 * POST /api/officers_transfer.php          → นำเข้าจากไฟล์ ZIP
 */
require_once __DIR__ . '/_common.php';

$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

function needAdmin($actor): void {
    if ($actor['role'] !== 'admin' && empty($actor['can_manage_users']))
        err('ไม่มีสิทธิ์ดำเนินการนี้', 403);
}

$avatarDir = __DIR__ . '/../uploads/avatars';

/* ================================================================
   GET — ส่งออกข้อมูลบุคลากรพร้อมภาพประจำตัวเป็น ZIP
   ================================================================ */
if ($method === 'GET') {
    needAdmin($actor);

    // ดึงข้อมูลบุคลากรทั้งหมด พร้อม avatar_path จาก users ที่เชื่อมโยง
    $rows = $db->query(
        "SELECT o.id, o.name, o.job_title, o.duty, o.group_name, o.init, o.active,
                u.avatar_path
         FROM officers o
         LEFT JOIN users u ON u.officer_id = o.id
         ORDER BY o.id"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!class_exists('ZipArchive'))
        err('ZipArchive ไม่พร้อมใช้งานบนเซิร์ฟเวอร์นี้', 500);

    $tmpFile = tempnam(sys_get_temp_dir(), 'officers_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
        err('ไม่สามารถสร้างไฟล์ ZIP ได้', 500);

    $officers = [];
    foreach ($rows as $row) {
        $entry = [
            'id'         => $row['id'],
            'name'       => $row['name'],
            'job_title'  => $row['job_title'],
            'duty'       => $row['duty'],
            'group_name' => $row['group_name'],
            'init'       => $row['init'],
            'active'     => (int)$row['active'],
            'avatar'     => null,
        ];

        // ใส่ภาพในโฟลเดอร์ avatars/ ภายใน ZIP โดยตั้งชื่อตาม officer id
        if ($row['avatar_path']) {
            $src = __DIR__ . '/../' . $row['avatar_path'];
            if (is_file($src)) {
                $ext = pathinfo($src, PATHINFO_EXTENSION);
                $zipName = 'avatars/' . $row['id'] . '.' . $ext;
                $zip->addFile($src, $zipName);
                $entry['avatar'] = $zipName;
            }
        }

        $officers[] = $entry;
    }

    $zip->addFromString('officers.json',
        json_encode($officers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    $zip->close();

    audit('officer_export', 'all', count($officers) . ' records');

    $filename = 'officers_' . date('Ymd_His') . '.zip';
    ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: no-store');
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

/* ================================================================
   POST — นำเข้าข้อมูลบุคลากรจาก ZIP
   ================================================================ */
if ($method === 'POST') {
    needAdmin($actor);

    if (!class_exists('ZipArchive'))
        err('ZipArchive ไม่พร้อมใช้งานบนเซิร์ฟเวอร์นี้', 500);

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
        err('กรุณาเลือกไฟล์ ZIP ที่ต้องการนำเข้า');

    $file = $_FILES['file'];
    if ($file['size'] > 50 * 1024 * 1024)
        err('ไฟล์ ZIP ขนาดใหญ่เกิน 50 MB');

    // ตรวจ magic bytes ว่าเป็น ZIP จริง (PK\x03\x04)
    $fh = fopen($file['tmp_name'], 'rb');
    $magic = fread($fh, 4);
    fclose($fh);
    if ($magic !== "PK\x03\x04")
        err('ไฟล์ที่อัปโหลดไม่ใช่ ZIP');

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true)
        err('ไม่สามารถเปิดไฟล์ ZIP ได้');

    // อ่าน officers.json จาก ZIP โดยตรง (ไม่ต้องแตกทั้งหมด)
    $json = $zip->getFromName('officers.json');
    if ($json === false)
        err('ไม่พบ officers.json ในไฟล์ ZIP — กรุณาใช้ไฟล์ที่ส่งออกจากระบบนี้เท่านั้น');

    $officers = json_decode($json, true);
    if (!is_array($officers) || empty($officers))
        err('officers.json ไม่ถูกต้องหรือว่างเปล่า');

    // สร้างโฟลเดอร์ avatars ถ้ายังไม่มี
    if (!is_dir($avatarDir))
        @mkdir($avatarDir, 0755, true);

    $stmtInsert = $db->prepare(
        "INSERT INTO officers (id, name, job_title, duty, group_name, init, active)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           name=VALUES(name), job_title=VALUES(job_title), duty=VALUES(duty),
           group_name=VALUES(group_name), init=VALUES(init), active=VALUES(active)"
    );
    $stmtAvatar = $db->prepare(
        "UPDATE users SET avatar_path=? WHERE officer_id=?"
    );
    $stmtOldAvatar = $db->prepare(
        "SELECT avatar_path FROM users WHERE officer_id=?"
    );

    $imported = 0; $avatarsImported = 0; $errors = [];

    foreach ($officers as $idx => $o) {
        $oid  = trim($o['id']   ?? '');
        $name = trim($o['name'] ?? '');
        if (!$oid || !$name) {
            $errors[] = "แถวที่ " . ($idx + 1) . ": ขาด id หรือ name";
            continue;
        }

        // กรองอักขระอันตราย ป้องกัน path traversal
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $oid)) {
            $errors[] = "แถวที่ " . ($idx + 1) . ": รหัสบุคลากร '{$oid}' มีอักขระที่ไม่อนุญาต";
            continue;
        }

        try {
            $stmtInsert->execute([
                $oid,
                $name,
                $o['job_title']  ?: null,
                $o['duty']       ?: null,
                $o['group_name'] ?: null,
                $o['init']       ?: null,
                isset($o['active']) ? (int)$o['active'] : 1,
            ]);
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "แถวที่ " . ($idx + 1) . " ({$oid}): " . $e->getMessage();
            continue;
        }

        // นำเข้าภาพประจำตัว (ถ้ามี)
        if (!empty($o['avatar'])) {
            // ป้องกัน path traversal ใน zipName
            $zipEntry = ltrim(str_replace('..', '', $o['avatar']), '/\\');
            if (!str_starts_with($zipEntry, 'avatars/')) {
                $errors[] = "แถวที่ " . ($idx + 1) . " ({$oid}): avatar path ไม่ถูกต้อง";
                continue;
            }

            $imgData = $zip->getFromName($zipEntry);
            if ($imgData === false) continue; // ไฟล์ภาพไม่อยู่ใน ZIP — ข้าม

            // ตรวจว่าเป็นภาพจริงด้วย getimagesizefromstring
            $imgInfo = @getimagesizefromstring($imgData);
            if ($imgInfo === false) {
                $errors[] = "แถวที่ " . ($idx + 1) . " ({$oid}): ไฟล์ภาพไม่ถูกต้อง";
                continue;
            }
            $extByMime = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!isset($extByMime[$imgInfo[2]])) {
                $errors[] = "แถวที่ " . ($idx + 1) . " ({$oid}): ประเภทภาพไม่รองรับ";
                continue;
            }

            // ลบภาพเดิมของ user ที่เชื่อมโยงกับบุคลากรนี้
            $stmtOldAvatar->execute([$oid]);
            $oldPath = $stmtOldAvatar->fetchColumn();
            if ($oldPath) {
                $oldFull = __DIR__ . '/../' . $oldPath;
                if (is_file($oldFull)) @unlink($oldFull);
            }

            $newName = bin2hex(random_bytes(16)) . '.' . $extByMime[$imgInfo[2]];
            $newPath = $avatarDir . '/' . $newName;
            if (file_put_contents($newPath, $imgData) !== false) {
                $webPath = 'uploads/avatars/' . $newName;
                $stmtAvatar->execute([$webPath, $oid]);
                $avatarsImported++;
            }
        }
    }

    $zip->close();
    audit('officer_import', 'all', "นำเข้า {$imported} รายการ, ภาพ {$avatarsImported} รูป");

    json_out([
        'ok'              => true,
        'imported'        => $imported,
        'avatars_imported'=> $avatarsImported,
        'errors'          => $errors,
    ]);
}

err('Method not allowed', 405);
