<?php
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed', 405);
if (empty($_FILES['file'])) err('ไม่พบไฟล์');

$file     = $_FILES['file'];
$origName = basename($file['name']);
$maxSize  = 20 * 1024 * 1024;

if ($file['error'] !== UPLOAD_ERR_OK) err('อัปโหลดไฟล์ล้มเหลว (error code: ' . $file['error'] . ')');
if ($file['size'] > $maxSize)         err('ไฟล์ขนาดใหญ่เกิน 20 MB');

$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf','jpg','jpeg','png','zip','docx','xlsx'], true)) {
    err('ประเภทไฟล์ไม่รองรับ — รองรับ: PDF, JPG, PNG, ZIP, DOCX, XLSX');
}

$tmpDir = __DIR__ . '/../uploads/tmp/';
if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true)) {
    err('ไม่สามารถสร้างโฟลเดอร์ชั่วคราวได้', 500);
}

$storedName = bin2hex(random_bytes(16)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $tmpDir . $storedName)) {
    err('บันทึกไฟล์ล้มเหลว', 500);
}

$sizeLabel = $file['size'] >= 1048576
    ? round($file['size'] / 1048576, 1) . ' MB'
    : round($file['size'] / 1024, 0) . ' KB';

json_out(['ok' => true, 'tmp' => $storedName, 'orig' => $origName, 'size' => $sizeLabel]);
