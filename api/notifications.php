<?php
require_once __DIR__ . '/../api/_common.php';
require_auth();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$me     = (int)$_SESSION['user_id'];

/* ── GET: รายการแจ้งเตือน + จำนวนยังไม่อ่าน ── */
if ($method === 'GET') {
    $limit  = min((int)($_GET['limit'] ?? 50), 100);
    $offset = (int)($_GET['offset'] ?? 0);

    $stmt = $db->prepare("
        SELECT id, case_id, notif_type, title, body, read_at, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$me, $limit, $offset]);
    $items = $stmt->fetchAll();

    $uStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL");
    $uStmt->execute([$me]);
    $unread = (int)$uStmt->fetchColumn();

    json_out(['items' => $items, 'unread' => $unread]);
    return;
}

/* ── PATCH: ทำเครื่องหมายอ่านแล้ว ── */
if ($method === 'PATCH') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    // mark all
    if (!empty($body['all'])) {
        $db->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL")
           ->execute([$me]);
        json_out(['ok' => true]);
        return;
    }

    // mark one by id
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) err('ต้องระบุ id');

    $db->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL")
       ->execute([$id, $me]);
    json_out(['ok' => true]);
    return;
}

err('Method not allowed', 405);
