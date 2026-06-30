<?php
/* ============================================================
   api/reports.php — ข้อมูลสรุปสำหรับศูนย์รายงาน
   GET ?action=audit — สรุป audit log + PDPA (managers)
   ============================================================ */
require_once __DIR__ . '/_common.php';

$auth = require_auth();
if (!in_array($auth['role'], ['admin','dir_legal','dir_admin','secretary','deputy_secretary'], true)) {
    err('Forbidden', 403);
}
$db     = getDB();
$action = $_GET['action'] ?? '';

if ($action === 'audit') {
    $since = date('Y-m-d', strtotime('-30 days'));

    // จำนวนเหตุการณ์ทั้งหมด 30 วันล่าสุด
    $total = (int)$db->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= " . $db->quote($since))->fetchColumn();

    // แยกตามประเภท action
    $byAction = $db->prepare("SELECT action, COUNT(*) AS n FROM audit_log WHERE created_at >= ? GROUP BY action ORDER BY n DESC");
    $byAction->execute([$since]);
    $byAction = $byAction->fetchAll();

    // ผู้ใช้ที่มีกิจกรรมสูงสุด
    $topUsers = $db->prepare("
        SELECT COALESCE(u.display_name, CONCAT('user#', a.user_id), 'ระบบ/สาธารณะ') AS name, COUNT(*) AS n
        FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
        WHERE a.created_at >= ? GROUP BY a.user_id ORDER BY n DESC LIMIT 8");
    $topUsers->execute([$since]);
    $topUsers = $topUsers->fetchAll();

    // สรุป PDPA จากตาราง cases
    $pdpa = $db->query("
        SELECT
          COUNT(*) AS total_cases,
          SUM(anon = 1) AS anon_cases,
          SUM(cls <> 'public') AS classified_cases
        FROM cases")->fetch();

    json_out([
        'since'      => $since,
        'total'      => $total,
        'by_action'  => $byAction,
        'top_users'  => $topUsers,
        'pdpa'       => [
            'total_cases'      => (int)($pdpa['total_cases'] ?? 0),
            'anon_cases'       => (int)($pdpa['anon_cases'] ?? 0),
            'classified_cases' => (int)($pdpa['classified_cases'] ?? 0),
        ],
    ]);
}

err('action ไม่ถูกต้อง', 400);
