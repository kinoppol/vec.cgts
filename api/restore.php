<?php
/* ============================================================
   api/restore.php — นำเข้าข้อมูลเพื่อกู้คืนจากไฟล์สำรอง (.zip)
     POST multipart: file=<zip>, overwrite=0|1
   อ่าน zip ด้วย PHP ล้วน (store + deflate ถ้ามี zlib) ไม่ต้องมี ext zip
   สิทธิ์: admin / dir_admin กู้คืนได้ทั้งหมด (เขียนทับได้)
           head_secretary กู้คืนเฉพาะเรื่องธุรการทั่วไป (ไม่เขียนทับ)
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth = require_auth();
$role = $auth['role'];
$canAll = in_array($role, ['admin', 'dir_admin'], true);
if (!$canAll && $role !== 'head_secretary') err('ไม่มีสิทธิ์นำเข้าข้อมูล', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Method not allowed', 405);
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) err('ไม่พบไฟล์สำรอง (.zip)');

$overwrite = !empty($_POST['overwrite']) && $canAll; // เขียนทับได้เฉพาะ admin/dir_admin

$tmp = $_FILES['file']['tmp_name'];

/* ── ZIP reader (store + deflate) แบบ PHP ล้วน ─────────────── */
function zip_entries(string $path): array {
    $fh = fopen($path, 'rb');
    if (!$fh) err('เปิดไฟล์ไม่ได้');
    $size = filesize($path);
    $maxBack = min($size, 22 + 65535);
    fseek($fh, $size - $maxBack);
    $tail = fread($fh, $maxBack);
    $p = strrpos($tail, "PK\x05\x06");
    if ($p === false) { fclose($fh); err('ไฟล์ที่อัปโหลดไม่ใช่ .zip ที่ถูกต้อง'); }
    $eocd = unpack('Vsig/vdisk/vcddisk/ventries/vtotal/Vcdsize/Vcdoff/vcomment', substr($tail, $p, 22));
    fseek($fh, $eocd['cdoff']);
    $entries = [];
    for ($i = 0; $i < $eocd['total']; $i++) {
        $hdr = fread($fh, 46);
        if (strlen($hdr) < 46) break;
        $c = unpack('Vsig/vver/vverneed/vflags/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vusize/vnamelen/vextralen/vcommentlen/vdisk/vinattr/Vexattr/Voffset', $hdr);
        if ($c['sig'] !== 0x02014b50) break;
        $name = fread($fh, $c['namelen']);
        if ($c['extralen'])   fseek($fh, $c['extralen'], SEEK_CUR);
        if ($c['commentlen']) fseek($fh, $c['commentlen'], SEEK_CUR);
        $entries[$name] = ['method' => $c['method'], 'csize' => $c['csize'], 'offset' => $c['offset']];
    }
    fclose($fh);
    return $entries;
}
function zip_data(string $path, array $e): string {
    $fh = fopen($path, 'rb');
    fseek($fh, $e['offset']);
    $lh = fread($fh, 30);
    $l = unpack('Vsig/vver/vflags/vmethod/vmtime/vmdate/Vcrc/Vcsize/Vusize/vnamelen/vextralen', $lh);
    fseek($fh, $l['namelen'] + $l['extralen'], SEEK_CUR);
    $data = $e['csize'] > 0 ? fread($fh, $e['csize']) : '';
    fclose($fh);
    if ($e['method'] === 0) return $data;
    if ($e['method'] === 8) {
        if (!function_exists('gzinflate')) err('ไฟล์ zip ถูกบีบอัด — เซิร์ฟเวอร์ไม่รองรับ (ต้องมี zlib)');
        return gzinflate($data);
    }
    err('วิธีบีบอัดในไฟล์ zip ไม่รองรับ');
}

$entries = zip_entries($tmp);
if (!$entries) err('ไม่พบข้อมูลในไฟล์สำรอง');

// จัดกลุ่มตามโฟลเดอร์ (เลขที่สำนวน)
$byFolder = [];
foreach ($entries as $name => $e) {
    if ($name === '_manifest.json') continue;
    $slash = strpos($name, '/');
    if ($slash === false) continue;
    $folder = substr($name, 0, $slash);
    $rest   = substr($name, $slash + 1);
    if ($rest === '' || substr($rest, -1) === '/') continue; // ข้ามรายการโฟลเดอร์
    $byFolder[$folder][$rest] = $e;
}

$db = getDB();
$caseCols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
$evCols   = $db->query("SHOW COLUMNS FROM case_events")->fetchAll(PDO::FETCH_COLUMN);
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

$imported = [];
$skipped  = [];
$errors   = [];

foreach ($byFolder as $folder => $files) {
    if (empty($files['case.json'])) { $skipped[] = "$folder (ไม่มี case.json)"; continue; }
    try {
        $detail = json_decode(zip_data($tmp, $files['case.json']), true);
        if (!is_array($detail) || empty($detail['case']['id'])) { $skipped[] = "$folder (case.json ไม่ถูกต้อง)"; continue; }
        $caseData = $detail['case'];
        $caseId   = $caseData['id'];
        $track    = $detail['work_track'] ?? 'legal';

        // สิทธิ์ตามสาย: head_secretary กู้คืนได้เฉพาะเรื่องธุรการทั่วไป
        if (!$canAll && $track !== 'general') { $skipped[] = "$caseId (นอกสิทธิ์ — ไม่ใช่เรื่องธุรการทั่วไป)"; continue; }

        // ตรวจว่ามีอยู่แล้วหรือยัง
        $ex = $db->prepare("SELECT stored_name FROM case_files WHERE case_id = ?");
        $chk = $db->prepare("SELECT id FROM cases WHERE id = ?");
        $chk->execute([$caseId]);
        $exists = (bool)$chk->fetchColumn();
        if ($exists && !$overwrite) { $skipped[] = "$caseId (มีอยู่แล้ว)"; continue; }

        $db->beginTransaction();
        if ($exists) {
            // ลบไฟล์เดิมบนดิสก์ก่อน แล้วลบ case (cascade ลบ events/files)
            $ex->execute([$caseId]);
            foreach ($ex->fetchAll(PDO::FETCH_COLUMN) as $sn) { if ($sn && is_file($uploadDir . $sn)) @unlink($uploadDir . $sn); }
            $db->prepare("DELETE FROM cases WHERE id = ?")->execute([$caseId]);
        }

        // insert case (เฉพาะคอลัมน์ที่มีอยู่จริง)
        $cols = array_values(array_intersect(array_keys($caseData), $caseCols));
        $ph   = implode(',', array_fill(0, count($cols), '?'));
        $cl   = implode(',', array_map(fn($c) => "`$c`", $cols));
        $vals = array_map(fn($c) => $caseData[$c], $cols);
        $db->prepare("INSERT INTO cases ($cl) VALUES ($ph)")->execute($vals);

        // insert events
        $hasStep = in_array('step_key', $evCols);
        foreach (($detail['events'] ?? []) as $i => $evt) {
            $sql = "INSERT INTO case_events (case_id, title, actor, moment, detail, ev_status, icon, sort_order"
                 . ($hasStep ? ", step_key, started_at, completed_at" : "") . ")"
                 . " VALUES (?,?,?,?,?,?,?,?" . ($hasStep ? ",?,?,?" : "") . ")";
            $args = [$caseId, $evt['title'] ?? '', $evt['actor'] ?? null, $evt['moment'] ?? null,
                     $evt['detail'] ?? null, $evt['ev_status'] ?? 'done', 'dot', $i + 1];
            if ($hasStep) { $args[] = $evt['step_key'] ?? null; $args[] = $evt['started_at'] ?? null; $args[] = $evt['completed_at'] ?? null; }
            $db->prepare($sql)->execute($args);
        }

        // insert files (คัดลอกไฟล์จริงจาก zip)
        $nFiles = 0;
        foreach (($detail['files'] ?? []) as $f) {
            $bp = $f['backup_path'] ?? '';
            if ($bp === '' || empty($files[$bp])) continue; // ไม่มีไฟล์จริงใน zip
            $bytes  = zip_data($tmp, $files[$bp]);
            $ext    = strtolower(pathinfo($f['original_name'] ?? $bp, PATHINFO_EXTENSION));
            $stored = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
            if (file_put_contents($uploadDir . $stored, $bytes) === false) continue;
            $db->prepare("INSERT INTO case_files (case_id, filename, stored_name, size_label, cls) VALUES (?,?,?,?,?)")
               ->execute([$caseId, $f['original_name'] ?? ('file.' . $ext), $stored, $f['size'] ?? null, $f['classification'] ?? 'public']);
            $nFiles++;
        }

        $db->commit();
        $imported[] = ['id' => $caseId, 'files' => $nFiles, 'overwritten' => $exists];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        $errors[] = "$folder: " . $e->getMessage();
    }
}

audit('restore', $overwrite ? 'overwrite' : 'skip', 'imported=' . count($imported) . ' skipped=' . count($skipped) . ' errors=' . count($errors));

json_out([
    'ok'       => true,
    'imported' => $imported,
    'skipped'  => $skipped,
    'errors'   => $errors,
]);
