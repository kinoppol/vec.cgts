<?php
require_once __DIR__ . '/_common.php';

$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

/* ----------------------------------------------------------------
   GET /api/role_labels.php — คืน map {role: label}
   เปิดให้ทุก role ที่ล็อกอินแล้ว
---------------------------------------------------------------- */
if ($method === 'GET') {
    $rows = $db->query(
        "SELECT role, label FROM role_labels
         ORDER BY FIELD(role,'officer','dir_legal','dir_admin','secretary','deputy_secretary','admin')"
    )->fetchAll();
    $map = [];
    foreach ($rows as $r) $map[$r['role']] = $r['label'];
    json_out($map);
}

/* ----------------------------------------------------------------
   POST /api/role_labels.php — บันทึกชื่อบทบาท (admin เท่านั้น)
---------------------------------------------------------------- */
if ($method === 'POST') {
    if ($actor['role'] !== 'admin') err('ไม่มีสิทธิ์แก้ไขชื่อบทบาท', 403);

    $b     = json_decode(file_get_contents('php://input'), true) ?? [];
    $role  = trim($b['role']  ?? '');
    $label = trim($b['label'] ?? '');

    $valid = ['officer','dir_legal','dir_admin','secretary','deputy_secretary','admin'];
    if (!in_array($role, $valid)) err('role ไม่ถูกต้อง');
    if ($label === '')            err('ชื่อบทบาทต้องไม่ว่างเปล่า');

    $db->prepare(
        "INSERT INTO role_labels (role, label) VALUES (?,?)
         ON DUPLICATE KEY UPDATE label=?"
    )->execute([$role, $label, $label]);

    audit('role_label_update', $role, $label);
    json_out(['role' => $role, 'label' => $label]);
}

err('Method not allowed', 405);
