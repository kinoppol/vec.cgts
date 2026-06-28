<?php
/* ============================================================
   api/exec_dashboard.php — สถิติสำหรับผู้บริหาร
   GET — ต้องล็อกอิน (dir_legal, deputy_secretary, secretary, admin)
   ============================================================ */
require_once __DIR__ . '/_common.php';

$actor = require_auth();
if (!in_array($actor['role'], ['admin','dir_legal','dir_admin','deputy_secretary','secretary'])) {
    err('ไม่มีสิทธิ์เข้าถึง', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') err('Method not allowed', 405);

$db    = getDB();
$today = date('Y-m-d');

/* ── helper ── */
$active_statuses = "('received','screening','case','assigned','investigating','reporting')";

/* ── 1. KPI counts ─────────────────────────────────────────── */
// ทั้งหมด (active)
$total = (int)$db->query("SELECT COUNT(*) FROM cases WHERE status IN $active_statuses")->fetchColumn();

// ครบกำหนดวันนี้
$due_today = (int)$db->query("
    SELECT COUNT(*) FROM cases
    WHERE status IN $active_statuses AND due_date = '$today'
")->fetchColumn();

// เกินกำหนด
$overdue = (int)$db->query("
    SELECT COUNT(*) FROM cases
    WHERE status IN $active_statuses AND due_date < '$today'
")->fetchColumn();

// ยังไม่เริ่ม (received)
$not_started = (int)$db->query("
    SELECT COUNT(*) FROM cases WHERE status = 'received'
")->fetchColumn();

// ปิดแล้ว (เดือนนี้)
$closed_month = (int)$db->query("
    SELECT COUNT(*) FROM cases
    WHERE status IN ('closed','rejected')
    AND DATE_FORMAT(updated_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
")->fetchColumn();

// ค้างเกิน N วัน (นับจาก received_date หรือ created_at)
$days_col = "COALESCE(received_date, DATE(created_at))";
$pending30 = (int)$db->query("
    SELECT COUNT(*) FROM cases
    WHERE status IN $active_statuses
    AND DATEDIFF('$today', $days_col) > 30
")->fetchColumn();
$pending60 = (int)$db->query("
    SELECT COUNT(*) FROM cases
    WHERE status IN $active_statuses
    AND DATEDIFF('$today', $days_col) > 60
")->fetchColumn();
$pending90 = (int)$db->query("
    SELECT COUNT(*) FROM cases
    WHERE status IN $active_statuses
    AND DATEDIFF('$today', $days_col) > 90
")->fetchColumn();

/* ── 2. By officer ─────────────────────────────────────────── */
$by_officer = $db->query("
    SELECT
        o.id, o.name, o.init, o.group_name,
        COUNT(c.id) AS total,
        SUM(CASE WHEN c.due_date < '$today' THEN 1 ELSE 0 END) AS overdue,
        SUM(CASE WHEN c.due_date = '$today' THEN 1 ELSE 0 END) AS due_today,
        SUM(CASE WHEN c.status IN ('closed','rejected') THEN 1 ELSE 0 END) AS closed
    FROM officers o
    LEFT JOIN cases c ON c.assignee_id = o.id
    WHERE o.active = 1
    GROUP BY o.id, o.name, o.init, o.group_name
    ORDER BY total DESC
")->fetchAll();

/* ── 3. By track (สายงาน) ─────────────────────────────────── */
$by_track = $db->query("
    SELECT track, COUNT(*) AS total,
        SUM(CASE WHEN status IN ('closed','rejected') THEN 1 ELSE 0 END) AS closed
    FROM cases GROUP BY track
")->fetchAll();

/* ── 4. By category ──────────────────────────────────────────  */
$by_cat = $db->query("
    SELECT COALESCE(cat,'ไม่ระบุ') AS cat, COUNT(*) AS total
    FROM cases
    WHERE status IN $active_statuses
    GROUP BY cat ORDER BY total DESC LIMIT 15
")->fetchAll();

/* ── 5. By province / agency (top agencies) ──────────────────  */
$by_agency = $db->query("
    SELECT COALESCE(agency,'ไม่ระบุ') AS agency, COUNT(*) AS total
    FROM cases
    WHERE status IN $active_statuses
    GROUP BY agency ORDER BY total DESC LIMIT 20
")->fetchAll();

/* ── 6. Case Aging distribution ──────────────────────────────  */
$aging = $db->query("
    SELECT
        SUM(CASE WHEN DATEDIFF('$today',$days_col) BETWEEN 0  AND 15  THEN 1 ELSE 0 END) AS d0_15,
        SUM(CASE WHEN DATEDIFF('$today',$days_col) BETWEEN 16 AND 30  THEN 1 ELSE 0 END) AS d16_30,
        SUM(CASE WHEN DATEDIFF('$today',$days_col) BETWEEN 31 AND 60  THEN 1 ELSE 0 END) AS d31_60,
        SUM(CASE WHEN DATEDIFF('$today',$days_col) BETWEEN 61 AND 90  THEN 1 ELSE 0 END) AS d61_90,
        SUM(CASE WHEN DATEDIFF('$today',$days_col) > 90               THEN 1 ELSE 0 END) AS d90plus
    FROM cases WHERE status IN $active_statuses
")->fetch();

/* ── 7. By status breakdown ──────────────────────────────────  */
$by_status = $db->query("
    SELECT status, COUNT(*) AS total FROM cases GROUP BY status ORDER BY total DESC
")->fetchAll();

/* ── 8. Monthly trend (12 เดือนล่าสุด) ──────────────────────  */
$monthly = $db->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym,
           COUNT(*) AS received,
           SUM(CASE WHEN status IN ('closed','rejected') THEN 1 ELSE 0 END) AS closed
    FROM cases
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY ym ORDER BY ym ASC
")->fetchAll();

/* ── 9. SLA summary ──────────────────────────────────────────  */
$sla_summary = $db->query("
    SELECT sla, COUNT(*) AS total FROM cases
    WHERE status IN $active_statuses GROUP BY sla
")->fetchAll();

json_out([
    'generated_at' => date('c'),
    'kpi' => [
        'total'        => $total,
        'due_today'    => $due_today,
        'overdue'      => $overdue,
        'not_started'  => $not_started,
        'closed_month' => $closed_month,
        'pending30'    => $pending30,
        'pending60'    => $pending60,
        'pending90'    => $pending90,
    ],
    'by_officer' => $by_officer,
    'by_track'   => $by_track,
    'by_cat'     => $by_cat,
    'by_agency'  => $by_agency,
    'aging'      => $aging,
    'by_status'  => $by_status,
    'monthly'    => $monthly,
    'sla_summary'=> $sla_summary,
]);
