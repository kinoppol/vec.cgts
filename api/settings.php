<?php
require_once __DIR__ . '/_common.php';

$auth   = require_auth();
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

if ($auth['role'] !== 'admin') err('Forbidden', 403);

if ($method === 'GET') {
    $rows = $db->query("SELECT `key`, `value` FROM app_settings ORDER BY `key`")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['key']] = $r['value'];
    json_out($out);
}

if ($method === 'PATCH') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['case_id_prefix'];
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
