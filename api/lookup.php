<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_purezip.php';
$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$VALID_CATS = ['group_name', 'job_title'];

function needAdmin($actor) {
    if ($actor['role'] !== 'admin' && empty($actor['can_manage_users']))
        err('ไม่มีสิทธิ์จัดการรายการอ้างอิง', 403);
}

/* ── GET ?action=export — ส่งออก ZIP ────────────────────── */
if ($method === 'GET' && ($_GET['action'] ?? '') === 'export') {
    needAdmin($actor);

    $rows = $db->query(
        "SELECT id, category, name, sort_order, active
         FROM lookup_items
         ORDER BY category, sort_order, name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $bycat = [];
    foreach ($rows as $r) {
        $bycat[$r['category']][] = [
            'id'         => (int)$r['id'],
            'name'       => $r['name'],
            'sort_order' => (int)$r['sort_order'],
            'active'     => (int)$r['active'],
        ];
    }

    $zip = new PureZip();
    $zip->addFromString('lookups.json',
        json_encode([
            'exported_at' => date('c'),
            'categories'  => $bycat,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    $bytes = $zip->bytes();
    audit('lookup_export', 'all', count($rows) . ' items');

    $filename = 'lookups_' . date('Ymd_His') . '.zip';
    ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}

/* ── GET ?cat= — รายการในหมวดนั้น ───────────────────────── */
if ($method === 'GET') {
    global $VALID_CATS;
    $cat = trim($_GET['cat'] ?? '');
    if (!in_array($cat, $VALID_CATS)) err('category ไม่ถูกต้อง', 400);

    $stmt = $db->prepare(
        "SELECT id, name, sort_order FROM lookup_items
         WHERE category=? AND active=1
         ORDER BY sort_order, name"
    );
    $stmt->execute([$cat]);
    json_out($stmt->fetchAll());
}

/* ── POST ?action=import — นำเข้าจาก ZIP ────────────────── */
if ($method === 'POST' && ($_GET['action'] ?? '') === 'import') {
    needAdmin($actor);

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
        err('กรุณาเลือกไฟล์ ZIP ที่ต้องการนำเข้า');

    $file = $_FILES['file'];
    if ($file['size'] > 5 * 1024 * 1024) err('ไฟล์ ZIP ขนาดใหญ่เกิน 5 MB');

    $fh    = fopen($file['tmp_name'], 'rb');
    $magic = fread($fh, 4);
    fclose($fh);
    if ($magic !== "PK\x03\x04") err('ไฟล์ที่อัปโหลดไม่ใช่ ZIP');

    $zipData = file_get_contents($file['tmp_name']);
    $json    = PureZip::getFromName($zipData, 'lookups.json');
    if ($json === false) err('ไม่พบ lookups.json — กรุณาใช้ไฟล์ที่ส่งออกจากระบบนี้เท่านั้น');

    $data = json_decode($json, true);
    if (!isset($data['categories']) || !is_array($data['categories']))
        err('lookups.json ไม่ถูกต้อง');

    global $VALID_CATS;
    $stmt = $db->prepare(
        "INSERT INTO lookup_items (category, name, sort_order, active)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), active=VALUES(active)"
    );

    $imported = 0; $skipped = 0; $errors = [];

    foreach ($data['categories'] as $cat => $items) {
        if (!in_array($cat, $VALID_CATS)) { $errors[] = "category '{$cat}' ไม่รองรับ — ข้าม"; continue; }
        if (!is_array($items)) continue;
        foreach ($items as $idx => $item) {
            $name = trim($item['name'] ?? '');
            if (!$name) { $skipped++; continue; }
            try {
                $stmt->execute([
                    $cat, $name,
                    (int)($item['sort_order'] ?? 0),
                    isset($item['active']) ? (int)$item['active'] : 1,
                ]);
                $imported++;
            } catch (PDOException $e) {
                $errors[] = "{$cat}/{$name}: " . $e->getMessage();
            }
        }
    }

    audit('lookup_import', 'all', "นำเข้า {$imported} รายการ");
    json_out(['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
}

/* ── POST — เพิ่มรายการ ──────────────────────────────────── */
if ($method === 'POST') {
    global $VALID_CATS;
    needAdmin($actor);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $cat  = trim($body['category'] ?? '');
    $name = trim($body['name']     ?? '');
    if (!in_array($cat, $VALID_CATS)) err('category ไม่ถูกต้อง', 400);
    if (!$name) err('name จำเป็น', 400);

    /* ตรวจซ้ำในหมวดเดียวกัน */
    $dup = $db->prepare("SELECT id FROM lookup_items WHERE category=? AND name=? AND active=1");
    $dup->execute([$cat, $name]);
    if ($dup->fetch()) err("มีรายการ \"$name\" อยู่แล้ว", 409);

    $db->prepare("INSERT INTO lookup_items (category, name) VALUES (?,?)")->execute([$cat, $name]);
    $nid = (int)$db->lastInsertId();
    audit('lookup_create', $nid, "$cat: $name");

    $stmt = $db->prepare("SELECT id, name, sort_order FROM lookup_items WHERE id=?");
    $stmt->execute([$nid]);
    json_out($stmt->fetch());
}

/* ── PATCH ?id= — แก้ไขชื่อ ─────────────────────────────── */
if ($method === 'PATCH' && $id) {
    needAdmin($actor);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($body['name'] ?? '');
    if (!$name) err('name จำเป็น', 400);

    $db->prepare("UPDATE lookup_items SET name=? WHERE id=?")->execute([$name, $id]);
    audit('lookup_update', $id, $name);

    $stmt = $db->prepare("SELECT id, name, sort_order FROM lookup_items WHERE id=?");
    $stmt->execute([$id]);
    json_out($stmt->fetch());
}

/* ── DELETE ?id= — ปิดใช้งาน (soft delete) ─────────────── */
if ($method === 'DELETE' && $id) {
    needAdmin($actor);
    $db->prepare("UPDATE lookup_items SET active=0 WHERE id=?")->execute([$id]);
    audit('lookup_delete', $id);
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
