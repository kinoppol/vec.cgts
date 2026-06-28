<?php
/* ============================================================
   api/file.php — เสิร์ฟไฟล์แนบอย่างปลอดภัย
   GET ?event=<event_id>  — ไฟล์แนบ event ขั้นตอน
   GET ?case=<stored_name> — ไฟล์แนบสำนวน (case_files)
   ต้องล็อกอินทุกกรณี; บันทึก audit log
   ============================================================ */
require_once __DIR__ . '/_common.php';

$actor = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') err('Method not allowed', 405);

$db          = getDB();
$uploadDir   = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR;
$inline      = (($_GET['inline'] ?? '0') === '1'); // ?inline=1 → view in browser

/* ── event attachment ── */
if (isset($_GET['event'])) {
    $eid  = (int)$_GET['event'];
    $stmt = $db->prepare('SELECT case_id, attachment_name, attachment_path FROM case_events WHERE id = ?');
    $stmt->execute([$eid]);
    $ev   = $stmt->fetch();
    if (!$ev || !$ev['attachment_path']) err('ไม่พบไฟล์', 404);

    $path     = $uploadDir . basename($ev['attachment_path']);
    $origName = $ev['attachment_name'] ?: 'document.pdf';
    $caseId   = $ev['case_id'];
}

/* ── case file (case_files table) ── */
elseif (isset($_GET['case'])) {
    $stored = basename($_GET['case']);
    $stmt   = $db->prepare('SELECT case_id, filename, stored_name FROM case_files WHERE stored_name = ?');
    $stmt->execute([$stored]);
    $cf     = $stmt->fetch();
    if (!$cf) err('ไม่พบไฟล์', 404);

    $path     = $uploadDir . $cf['stored_name'];
    $origName = $cf['filename'] ?: 'document';
    $caseId   = $cf['case_id'];
}

else {
    err('ต้องระบุ ?event= หรือ ?case=');
}

if (!file_exists($path) || !is_file($path)) err('ไม่พบไฟล์บนเซิร์ฟเวอร์', 404);

// ตรวจ path traversal
if (strpos(realpath($path), $uploadDir) !== 0) err('Access denied', 403);

audit('file_download', $caseId, basename($path));

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match($ext) {
    'pdf'  => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'zip'  => 'application/zip',
    default => 'application/octet-stream',
};

$disposition = ($inline && $ext === 'pdf') ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($origName) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
