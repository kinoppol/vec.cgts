<?php
require_once __DIR__ . '/_common.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') err('Method not allowed', 405);

$db = getDB();

// นับจำนวนสำนวนที่อยู่ในมือของแต่ละนิติกร
$rows = $db->query(
    "SELECT o.id, o.name, o.job_title AS role, o.group_name AS `group`, o.init,
            COUNT(c.id) AS `load`
     FROM officers o
     LEFT JOIN cases c ON c.assignee_id = o.id AND c.status NOT IN ('closed','rejected')
     GROUP BY o.id
     ORDER BY o.id"
)->fetchAll();

json_out($rows);
