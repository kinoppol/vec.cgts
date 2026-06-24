<?php
/* ============================================================
   api/public_stats.php — สถิติสาธารณะ (ไม่มีข้อมูลส่วนบุคคล)
   ============================================================ */
require_once __DIR__ . '/_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') err('Method not allowed', 405);

$db = getDB();

/* รวมทั้งหมด */
$total = (int)$db->query("SELECT COUNT(*) FROM cases")->fetchColumn();

/* แบ่งตาม track */
$rows = $db->query("SELECT track, COUNT(*) AS n FROM cases GROUP BY track")->fetchAll();
$byTrack = ['discipline' => 0, 'legal' => 0];
foreach ($rows as $r) $byTrack[$r['track']] = (int)$r['n'];

/* สถานะ: ปิดแล้ว / อยู่ระหว่างดำเนินการ / ปฏิเสธ */
$rows = $db->query("SELECT status, COUNT(*) AS n FROM cases GROUP BY status")->fetchAll();
$byStatus = [];
foreach ($rows as $r) $byStatus[$r['status']] = (int)$r['n'];
$closed   = $byStatus['closed']   ?? 0;
$rejected = $byStatus['rejected'] ?? 0;
$active   = $total - $closed - $rejected;

/* SLA ใน active cases (ไม่รวม closed/rejected) */
$slaRows = $db->query(
    "SELECT sla, COUNT(*) AS n FROM cases WHERE status NOT IN ('closed','rejected') GROUP BY sla"
)->fetchAll();
$bySla = ['g'=>0,'a'=>0,'r'=>0];
foreach ($slaRows as $r) $bySla[$r['sla']] = (int)$r['n'];
$activeTotal = array_sum($bySla);
$onTime = $activeTotal > 0 ? round($bySla['g'] / $activeTotal * 100) : 100;

/* ช่องทางยื่น (top 4 + อื่น ๆ) */
$chRows = $db->query(
    "SELECT COALESCE(NULLIF(TRIM(channel),''), 'ไม่ระบุ') AS ch, COUNT(*) AS n
     FROM cases GROUP BY ch ORDER BY n DESC LIMIT 5"
)->fetchAll();
$channels = array_map(fn($r) => ['name' => $r['ch'], 'count' => (int)$r['n']], $chRows);

/* เรื่องที่รับเข้าในปีปัจจุบัน (พ.ศ.) */
$thisYear   = date('Y') + 543;
$yearPrefix = 'CMP-' . $thisYear . '-%';
$stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE id LIKE ?");
$stmt->execute([$yearPrefix]);
$thisYearCount = (int)$stmt->fetchColumn();

json_out([
    'total'        => $total,
    'this_year'    => $thisYearCount,
    'closed'       => $closed,
    'active'       => $active,
    'rejected'     => $rejected,
    'on_time_pct'  => $onTime,
    'by_track'     => $byTrack,
    'channels'     => $channels,
    'updated_at'   => date('Y-m-d H:i'),
]);
