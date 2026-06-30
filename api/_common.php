<?php
// ใช้ร่วมกันใน API ทุกไฟล์
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('vec_cgts_sess');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ป้องกัน PHP error/warning หลุดออกมาปนกับ JSON response (ทำให้ฝั่ง client parse JSON พังทั้งหน้า)
ini_set('display_errors', '0');
ob_start();

function json_out(mixed $data, int $code = 200): never {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    json_out(['error' => 'เกิดข้อผิดพลาดที่ระบบ — กรุณาติดต่อผู้ดูแลระบบ'], 500);
});

function err(string $msg, int $code = 400): never {
    json_out(['error' => $msg], $code);
}

function require_auth(): array {
    if (empty($_SESSION['user_id'])) {
        err('Unauthorized', 401);
    }
    return ['id' => $_SESSION['user_id'], 'role' => $_SESSION['role']];
}

function require_user_manager(): array {
    $u = require_auth();
    if ($u['role'] !== 'admin' && $u['role'] !== 'dir_legal' && empty($_SESSION['can_manage_users'])) {
        err('Forbidden', 403);
    }
    return $u;
}

/**
 * เริ่ม SLA step อัตโนมัติ — set started_at=NOW(), ev_status='active' บน case_event ที่ตรง step_key
 * ถ้ายังไม่มี event row สำหรับ step นั้น จะ INSERT ใหม่โดยดึง sla_steps มาประกอบ
 */
function startSlaStep(PDO $db, string $caseId, string $stepKey): void {
    // ลอง UPDATE ก่อน (event มีอยู่แล้ว)
    $upd = $db->prepare(
        "UPDATE case_events SET started_at = COALESCE(started_at, NOW()), ev_status = 'active'
         WHERE case_id = ? AND step_key = ? AND started_at IS NULL"
    );
    $upd->execute([$caseId, $stepKey]);
    if ($upd->rowCount() > 0) return;

    // ถ้ายังไม่มี event row เลย (case เก่าก่อนระบบ SLA) ให้ INSERT
    $sp = $db->prepare("SELECT * FROM sla_steps WHERE step_key = ? AND active = 1");
    $sp->execute([$stepKey]);
    $step = $sp->fetch();
    if (!$step) return;

    $ex = $db->prepare("SELECT id FROM case_events WHERE case_id = ? AND step_key = ?");
    $ex->execute([$caseId, $stepKey]);
    if ($ex->fetch()) return; // มีอยู่แล้ว (started_at ไม่ใช่ NULL) ข้าม

    $db->prepare(
        "INSERT INTO case_events (case_id, title, ev_status, icon, sort_order, step_key, started_at)
         VALUES (?, ?, 'active', 'clock', ?, ?, NOW())"
    )->execute([$caseId, $step['label'], (int)$step['sort_order'], $stepKey]);
}

const ROLE_PRIORITY = ['officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin'];

/**
 * getRoleCandidates — รวมบทบาทจากทุกแหล่ง คืนเป็น array (ไม่ซ้ำ) เรียงจากสิทธิ์สูง→ต่ำ
 *   (1) บทบาทส่วนตัว  (2) บทบาทของทุกกลุ่มที่สังกัด  (3) บทบาทหัวหน้ากลุ่มที่ได้รับแต่งตั้ง
 */
function getRoleCandidates(PDO $db, int $userId, ?string $personalRole, ?string $groupName): array {
    $candidates = [];
    if ($personalRole) $candidates[] = $personalRole;

    if ($groupName) {
        try {
            $gr = $db->prepare("SELECT gr.role FROM group_roles gr JOIN groups g ON g.id = gr.group_id WHERE g.name = ?");
            $gr->execute([$groupName]);
            foreach ($gr->fetchAll(PDO::FETCH_COLUMN) as $r) { if ($r) $candidates[] = $r; }
        } catch (Throwable) {}
    }
    try {
        $lg = $db->prepare('SELECT leader_role FROM groups WHERE leader_id = ? AND leader_role IS NOT NULL');
        $lg->execute([$userId]);
        foreach ($lg->fetchAll(PDO::FETCH_COLUMN) as $r) { if ($r) $candidates[] = $r; }
    } catch (Throwable) {}

    // unique + เรียงตามสิทธิ์ (สูง→ต่ำ)
    $uniq = array_values(array_unique(array_filter($candidates, fn($c) => in_array($c, ROLE_PRIORITY, true))));
    usort($uniq, fn($a, $b) => array_search($b, ROLE_PRIORITY, true) - array_search($a, ROLE_PRIORITY, true));
    return $uniq ?: ['officer'];
}

/** resolveEffectiveRole — บทบาทสูงสุด (ตัวแรกของ candidates) */
function resolveEffectiveRole(PDO $db, int $userId, ?string $personalRole, ?string $groupName): string {
    return getRoleCandidates($db, $userId, $personalRole, $groupName)[0] ?? 'officer';
}

/** getLeaderGroups — กลุ่มที่ผู้ใช้เป็นหัวหน้า พร้อม leader_role (สำหรับแสดงชื่อกลุ่มต่อท้ายบทบาท) */
function getLeaderGroups(PDO $db, int $userId): array {
    try {
        $s = $db->prepare('SELECT name, leader_role FROM groups WHERE leader_id = ?');
        $s->execute([$userId]);
        return $s->fetchAll() ?: [];
    } catch (Throwable) { return []; }
}

function audit(string $action, ?string $target = null, ?string $detail = null): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO audit_log (user_id, action, target_id, detail, ip) VALUES (?,?,?,?,?)')
           ->execute([$_SESSION['user_id'] ?? null, $action, $target, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable) {}
}
