<?php
/* ============================================================
   api/upload_event.php — อัปโหลดไฟล์แนบสำหรับ event ขั้นตอน
   POST multipart: event_id (int), file (PDF only)
   ============================================================ */
require_once __DIR__ . '/_common.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed', 405);

$eventId = (int)($_POST['event_id'] ?? 0);
if (!$eventId) err('ต้องระบุ event_id');

$db   = getDB();
$stmt = $db->prepare('SELECT id, case_id FROM case_events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();
if (!$event) err('ไม่พบ event', 404);

if (empty($_FILES['file'])) err('ไม่พบไฟล์');

$file     = $_FILES['file'];
$origName = basename($file['name']);
$maxSize  = 20 * 1024 * 1024; // 20 MB

if ($file['error'] !== UPLOAD_ERR_OK) err('อัปโหลดไฟล์ล้มเหลว');
if ($file['size'] > $maxSize)         err('ไฟล์ขนาดใหญ่เกิน 20 MB');

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext !== 'pdf') err('รองรับเฉพาะไฟล์ PDF เท่านั้น');

// ตรวจ magic bytes PDF
$fh = fopen($file['tmp_name'], 'rb');
$magic = fread($fh, 4);
fclose($fh);
if ($magic !== '%PDF') err('ไฟล์ไม่ใช่ PDF จริง');

$uploadDir  = __DIR__ . '/../uploads/';
$storedName = 'evt_' . bin2hex(random_bytes(12)) . '.pdf';
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
    err('บันทึกไฟล์ล้มเหลว', 500);
}

$sizeLabel = $file['size'] >= 1048576
    ? round($file['size'] / 1048576, 1) . ' MB'
    : round($file['size'] / 1024, 0) . ' KB';

// บันทึก attachment ลง case_events
$db->prepare("UPDATE case_events SET attachment_name = ?, attachment_path = ?, attachment_size = ? WHERE id = ?")
   ->execute([$origName, $storedName, $sizeLabel, $eventId]);

audit('event_file_upload', $event['case_id'], "event={$eventId} file={$origName}");

json_out([
    'attachment_name' => $origName,
    'attachment_path' => $storedName,
    'attachment_size' => $sizeLabel,
]);
