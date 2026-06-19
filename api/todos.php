<?php
require_once __DIR__ . '/_common.php';

$actor = require_auth();
$uid   = (int)$actor['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM todo_items WHERE user_id = ? ORDER BY done ASC, created_at DESC');
    $stmt->execute([$uid]);
    json_out($stmt->fetchAll());
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $title  = trim($body['title'] ?? '');
    if ($title === '') err('กรุณาระบุชื่องาน');
    $detail = trim($body['detail'] ?? '') ?: null;

    $db = getDB();
    $db->prepare('INSERT INTO todo_items (user_id, title, detail) VALUES (?,?,?)')->execute([$uid, $title, $detail]);
    $id   = (int)$db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM todo_items WHERE id = ?');
    $stmt->execute([$id]);
    json_out($stmt->fetch(), 201);
}

if ($method === 'PATCH') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) err('ไม่ระบุ id');

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $db   = getDB();

    $check = $db->prepare('SELECT id, done FROM todo_items WHERE id = ? AND user_id = ?');
    $check->execute([$id, $uid]);
    $item = $check->fetch();
    if (!$item) err('ไม่พบรายการ', 404);

    $sets = []; $params = [];

    if (array_key_exists('done', $body)) {
        $done = $body['done'] ? 1 : 0;
        $sets[]   = 'done = ?';  $params[] = $done;
        if ($done && !$item['done']) {
            $sets[] = 'completed_at = NOW()';
        } elseif (!$done) {
            $sets[] = 'completed_at = NULL';
        }
    }
    if (array_key_exists('title', $body)) {
        $title = trim($body['title']);
        if ($title === '') err('กรุณาระบุชื่องาน');
        $sets[] = 'title = ?'; $params[] = $title;
    }
    if (array_key_exists('detail', $body)) {
        $sets[] = 'detail = ?'; $params[] = (trim($body['detail']) ?: null);
    }

    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE todo_items SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    $stmt = $db->prepare('SELECT * FROM todo_items WHERE id = ?');
    $stmt->execute([$id]);
    json_out($stmt->fetch());
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) err('ไม่ระบุ id');
    $db = getDB();
    $db->prepare('DELETE FROM todo_items WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    json_out(['ok' => true]);
}

err('Method not allowed', 405);
