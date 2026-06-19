<?php
require_once __DIR__ . '/_common.php';
$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$VALID_CATS = ['group_name', 'job_title'];

function needAdmin($actor) {
    if ($actor['role'] !== 'admin' && empty($actor['can_manage_users']))
        err('ไม่มีสิทธิ์จัดการรายการอ้างอิง', 403);
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
