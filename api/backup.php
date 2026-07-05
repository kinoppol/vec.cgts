<?php
/* ============================================================
   api/backup.php — สำรองข้อมูลสำนวนเป็นไฟล์ .zip (ตามช่วงเวลา)
     GET ?from=YYYY-MM-DD&to=YYYY-MM-DD&scope=legal|general|both
   สิทธิ์:
     admin / dir_admin  → สำรองได้ทั้งคดี (legal) และเรื่องธุรการทั่วไป (general)
     head_secretary     → สำรองได้เฉพาะเรื่องธุรการทั่วไป (general)
   โครงสร้างในไฟล์ zip: <เลขบัญชีของเรื่อง>/case.json + <เลขบัญชีของเรื่อง>/files/*
   สร้าง zip ด้วย PHP ล้วน (store method) ไม่ต้องมี ext zip
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth = require_auth();
$role = $auth['role'];
$canAll = in_array($role, ['admin', 'dir_admin'], true);
if (!$canAll && $role !== 'head_secretary') err('ไม่มีสิทธิ์สำรองข้อมูล', 403);

$from  = trim($_GET['from'] ?? '');
$to    = trim($_GET['to'] ?? '');
$scope = $_GET['scope'] ?? 'general';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    err('กรุณาระบุช่วงวันที่ให้ถูกต้อง (YYYY-MM-DD)');
}
if ($from > $to) err('วันที่เริ่มต้องไม่อยู่หลังวันที่สิ้นสุด');
// head_secretary ถูกจำกัดให้ general เท่านั้น
if (!$canAll) $scope = 'general';
if (!in_array($scope, ['legal', 'general', 'both'], true)) $scope = 'general';

$db = getDB();

// กลุ่มสายบริหารทั่วไป (หัวหน้ากลุ่ม = หัวหน้าธุรการ)
$genGroups = [];
try {
    $genGroups = $db->query(
        "SELECT g.name FROM `groups` g LEFT JOIN users u ON u.id = g.leader_id
         WHERE g.leader_role = 'head_secretary' OR u.role = 'head_secretary'
            OR EXISTS(SELECT 1 FROM group_roles gr WHERE gr.group_id = g.id AND gr.role = 'head_secretary')"
    )->fetchAll(PDO::FETCH_COLUMN);
    $genGroups = array_values(array_filter($genGroups));
} catch (Throwable) { $genGroups = []; }
$genSet = array_flip($genGroups);

// ดึงสำนวนตามช่วงวันรับเรื่อง
$caseCols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
$dateExpr = in_array('received_date', $caseCols) ? "COALESCE(received_date, DATE(created_at))" : "DATE(created_at)";
$stmt = $db->prepare("SELECT * FROM cases WHERE $dateExpr BETWEEN ? AND ? ORDER BY id");
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

// กรองตาม scope (general = assigned_group อยู่ในกลุ่มสายบริหารทั่วไป)
$cases = [];
foreach ($rows as $c) {
    $ag = $c['assigned_group'] ?? '';
    $isGen = $ag !== '' && $ag !== null && isset($genSet[$ag]);
    if ($scope === 'general' && !$isGen) continue;
    if ($scope === 'legal'   &&  $isGen) continue;
    $cases[] = $c;
}
if (!$cases) err('ไม่พบข้อมูลในช่วงเวลาที่เลือก', 404);

/* ── ZIP writer (store, PHP ล้วน) ─────────────────────────── */
$tmp = tempnam(sys_get_temp_dir(), 'vecbak');
$out = fopen($tmp, 'wb');
$central = '';
$offset  = 0;
$count   = 0;
$t = getdate();
$dosTime = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
$dosDate = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];

$writeEntry = function (string $name, ?string $data, ?string $path)
    use ($out, &$central, &$offset, &$count, $dosTime, $dosDate) {
    if ($data !== null) { $size = strlen($data); $crc = crc32($data); }
    else { $size = (int)@filesize($path); $crc = $size > 0 ? hexdec(hash_file('crc32b', $path)) : 0; }
    $nameLen = strlen($name);
    $lfh = pack('V', 0x04034b50) . pack('v', 20) . pack('v', 0x0800) . pack('v', 0)
         . pack('v', $dosTime) . pack('v', $dosDate) . pack('V', $crc)
         . pack('V', $size) . pack('V', $size) . pack('v', $nameLen) . pack('v', 0) . $name;
    fwrite($out, $lfh);
    if ($data !== null) { if ($data !== '') fwrite($out, $data); }
    elseif ($size > 0) { $in = fopen($path, 'rb'); stream_copy_to_stream($in, $out); fclose($in); }
    $central .= pack('V', 0x02014b50) . pack('v', 20) . pack('v', 20) . pack('v', 0x0800) . pack('v', 0)
         . pack('v', $dosTime) . pack('v', $dosDate) . pack('V', $crc)
         . pack('V', $size) . pack('V', $size) . pack('v', $nameLen) . pack('v', 0) . pack('v', 0)
         . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', $offset) . $name;
    $offset += strlen($lfh) + $size;
    $count++;
};

$safe = function (string $s): string {
    $s = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', "\0"], '_', $s);
    return trim($s) === '' ? 'unknown' : $s;
};

$uploadDir = __DIR__ . '/../uploads/';
$manifest = [
    'exported_at' => date('c'),
    'exported_by' => $auth['id'],
    'scope'       => $scope,
    'from'        => $from,
    'to'          => $to,
    'case_count'  => count($cases),
    'cases'       => [],
];

foreach ($cases as $c) {
    $folder = $safe($c['id']); // เลขบัญชีของเรื่อง (เลขที่สำนวน)
    $isGen  = isset($genSet[$c['assigned_group'] ?? '']);

    // เหตุการณ์ในไทม์ไลน์
    $ev = $db->prepare("SELECT title, actor, moment, detail, ev_status, step_key, started_at, completed_at
                        FROM case_events WHERE case_id = ? ORDER BY sort_order, id");
    $ev->execute([$c['id']]);
    $events = $ev->fetchAll();

    // ไฟล์แนบ
    $fq = $db->prepare("SELECT filename, stored_name, size_label, cls FROM case_files WHERE case_id = ? ORDER BY id");
    $fq->execute([$c['id']]);
    $fileRows = $fq->fetchAll();

    // เขียนไฟล์จริงลงโฟลเดอร์ files/ (กันชื่อชนด้วยเลขนำหน้า)
    $writeEntry("$folder/files/", '', null); // โฟลเดอร์ files ว่างไว้เสมอ
    $fileManifest = [];
    $i = 0;
    foreach ($fileRows as $f) {
        $i++;
        $stored = $f['stored_name'] ?? '';
        $backupName = sprintf('%02d_%s', $i, $safe($f['filename'] ?: ('file' . $i)));
        $exists = $stored && is_file($uploadDir . $stored);
        if ($exists) $writeEntry("$folder/files/$backupName", null, $uploadDir . $stored);
        $fileManifest[] = [
            'original_name' => $f['filename'],
            'backup_path'   => "files/$backupName",
            'size'          => $f['size_label'],
            'classification'=> $f['cls'],
            'file_present'  => $exists,
        ];
    }

    // ข้อมูลรายละเอียดของเรื่อง + ไฟล์
    $detail = [
        'case'       => $c,
        'work_track' => $isGen ? 'general' : 'legal',
        'events'     => $events,
        'files'      => $fileManifest,
    ];
    $writeEntry("$folder/case.json",
        json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), null);

    $manifest['cases'][] = [
        'id'         => $c['id'],
        'subject'    => $c['subject'] ?? '',
        'work_track' => $isGen ? 'general' : 'legal',
        'file_count' => count($fileManifest),
    ];
}

// manifest รวมของชุดสำรอง
$writeEntry('_manifest.json',
    json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), null);

// ปิดท้าย zip (central directory + end record)
$cdSize = strlen($central);
$cdOff  = $offset;
fwrite($out, $central);
fwrite($out, pack('V', 0x06054b50) . pack('v', 0) . pack('v', 0)
    . pack('v', $count) . pack('v', $count)
    . pack('V', $cdSize) . pack('V', $cdOff) . pack('v', 0));
fclose($out);

audit('backup', $scope, "from=$from to=$to cases=" . count($cases));

$fname = 'backup_' . $scope . '_' . $from . '_' . $to . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . filesize($tmp));
header('X-Backup-Count: ' . count($cases));
readfile($tmp);
@unlink($tmp);
exit;
