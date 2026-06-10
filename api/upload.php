<?php
require_once __DIR__ . '/_common.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed', 405);

$caseId = trim($_POST['case_id'] ?? '');
$cls    = $_POST['cls'] ?? 'internal';
if (!$caseId) err('ต้องระบุ case_id');

$db   = getDB();
$stmt = $db->prepare('SELECT id FROM cases WHERE id = ?');
$stmt->execute([$caseId]);
if (!$stmt->fetch()) err('ไม่พบสำนวน', 404);

if (empty($_FILES['file'])) err('ไม่พบไฟล์');

$file    = $_FILES['file'];
$origName = basename($file['name']);
$maxSize  = 20 * 1024 * 1024; // 20 MB

if ($file['error'] !== UPLOAD_ERR_OK)  err('อัปโหลดไฟล์ล้มเหลว');
if ($file['size'] > $maxSize)          err('ไฟล์ขนาดใหญ่เกิน 20 MB');

// whitelist extensions
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf','jpg','jpeg','png','zip','docx','xlsx'], true)) {
    err('ประเภทไฟล์ไม่รองรับ');
}

$uploadDir = __DIR__ . '/../uploads/';
$storedName = bin2hex(random_bytes(16)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
    err('บันทึกไฟล์ล้มเหลว', 500);
}

$sizeLabel = $file['size'] >= 1048576
    ? round($file['size'] / 1048576, 1) . ' MB'
    : round($file['size'] / 1024, 0) . ' KB';

$db->prepare(
    'INSERT INTO case_files (case_id, filename, stored_name, size_label, cls) VALUES (?,?,?,?,?)'
)->execute([$caseId, $origName, $storedName, $sizeLabel, $cls]);

audit('upload_file', $caseId, $origName);
json_out(['ok' => true, 'filename' => $origName, 'size' => $sizeLabel], 201);
