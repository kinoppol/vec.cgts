<?php
/* ============================================================
   api/impersonate.php — สวมสิทธิ์ผู้ใช้ (Admin เท่านั้น)
   POST   — เริ่มสวมสิทธิ์ผู้ใช้ที่ระบุ
   DELETE — คืนกลับสู่ session admin เดิม
   ============================================================ */
require_once __DIR__ . '/_common.php';

$actor  = require_auth();
$method = $_SERVER['REQUEST_METHOD'];

/* ── POST: เริ่มสวมสิทธิ์ ──────────────────────────────── */
if ($method === 'POST') {
    if ($actor['role'] !== 'admin') err('เฉพาะ admin เท่านั้น', 403);
    if (!empty($_SESSION['impersonator_id'])) err('กำลังสวมสิทธิ์อยู่แล้ว — กรุณาคืนสิทธิ์ก่อน', 400);

    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $target_id = (int)($body['user_id'] ?? 0);
    if (!$target_id) err('ต้องระบุ user_id');

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, display_name, role, group_name, init, can_manage_users, avatar_path FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$target_id]);
    $target = $stmt->fetch();
    if (!$target)                      err('ไม่พบผู้ใช้', 404);
    if ($target['id'] === $actor['id']) err('ไม่สามารถสวมสิทธิ์ตัวเองได้', 400);

    // บทบาทที่แท้จริงของ target (รวมบทบาทกลุ่ม/หัวหน้ากลุ่ม)
    $targetRole = resolveEffectiveRole($db, (int)$target['id'], $target['role'], $target['group_name']);
    if ($targetRole === 'admin')   err('ไม่สามารถสวมสิทธิ์ admin ด้วยกัน', 403);

    // บันทึก admin เดิมไว้
    $_SESSION['impersonator_id']   = (int)$actor['id'];
    $_SESSION['impersonator_name'] = $actor['display_name'];
    // เปลี่ยน session เป็น target
    $_SESSION['user_id']           = $target['id'];
    $_SESSION['role']              = $targetRole;
    $_SESSION['can_manage_users']  = (bool)$target['can_manage_users'];

    audit('impersonate_start', (string)$target['id'], "admin={$actor['id']} → user={$target['id']}");

    json_out([
        'id'               => $target['id'],
        'username'         => $target['username'],
        'display_name'     => $target['display_name'],
        'role'             => $targetRole,
        'init'             => $target['init'],
        'avatar_path'      => $target['avatar_path'],
        'can_manage_users' => (bool)$target['can_manage_users'],
        'is_impersonating' => true,
        'impersonator_id'  => (int)$actor['id'],
        'impersonator_name'=> $actor['display_name'],
    ]);
}

/* ── DELETE: คืนกลับสู่ admin ─────────────────────────── */
if ($method === 'DELETE') {
    if (empty($_SESSION['impersonator_id'])) err('ไม่ได้อยู่ในโหมดสวมสิทธิ์', 400);

    $admin_id   = (int)$_SESSION['impersonator_id'];
    $admin_name = $_SESSION['impersonator_name'] ?? '';

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, display_name, role, init, can_manage_users, avatar_path FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    if (!$admin) { session_destroy(); err('ไม่พบบัญชี admin เดิม', 404); }

    audit('impersonate_end', (string)$_SESSION['user_id'], "restored admin={$admin_id}");

    // คืน session กลับเป็น admin
    $_SESSION['user_id']          = $admin['id'];
    $_SESSION['role']             = $admin['role'];
    $_SESSION['can_manage_users'] = (bool)$admin['can_manage_users'];
    unset($_SESSION['impersonator_id'], $_SESSION['impersonator_name']);

    json_out([
        'id'               => $admin['id'],
        'username'         => $admin['username'],
        'display_name'     => $admin['display_name'],
        'role'             => $admin['role'],
        'init'             => $admin['init'],
        'avatar_path'      => $admin['avatar_path'],
        'can_manage_users' => (bool)$admin['can_manage_users'],
        'is_impersonating' => false,
    ]);
}

err('Method not allowed', 405);
