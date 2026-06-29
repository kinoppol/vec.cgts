<?php
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ทุก role ขอดู next_case_id ได้ (สำหรับ preview ในฟอร์มนำเข้า)
if ($method === 'GET' && isset($_GET['next_case_id'])) {
    try {
        $pfx     = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_prefix'")->fetchColumn();
        $nextSeq = $db->query("SELECT `value` FROM app_settings WHERE `key`='case_id_next_seq'")->fetchColumn();
    } catch (Throwable) { $pfx = null; $nextSeq = null; }
    $pfx    = preg_replace('/[^A-Za-z0-9ก-๙]/', '', $pfx ?: 'CMP');
    $year   = date('Y') + 543;
    $prefix = "{$pfx}-{$year}-";
    if ($nextSeq !== null && $nextSeq !== false && (int)$nextSeq > 0) {
        $seq = (int)$nextSeq;
    } else {
        $last = $db->prepare("SELECT id FROM cases WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
        $last->execute([$prefix . '%']);
        $last = $last->fetchColumn();
        $seq  = $last ? ((int)substr($last, -4) + 1) : 1;
    }
    json_out(['next_case_id' => $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT)]);
}

if ($auth['role'] !== 'admin') err('Forbidden', 403);

if ($method === 'GET') {
    $rows = $db->query("SELECT `key`, `value` FROM app_settings ORDER BY `key`")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    json_out($out);
}

if ($method === 'PATCH') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['case_id_prefix', 'case_id_next_seq'];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $b)) continue;
        $v = trim($b[$k] ?? '');
        $db->prepare("INSERT INTO app_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
           ->execute([$k, $v ?: null]);
    }
    audit('update_settings', null, json_encode(array_intersect_key($b, array_flip($allowed))));
    $rows = $db->query("SELECT `key`, `value` FROM app_settings ORDER BY `key`")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    json_out($out);
}

err('Method not allowed', 405);
