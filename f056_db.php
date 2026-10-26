<?php
// F056 Database access proof
header('Content-Type: text/plain; charset=utf-8');

require_once 'config/db.php';

echo "=== DB CONNECTION SUCCESS ===\n";
echo "Connected to: " . DB_HOST . ":" . DB_PORT . "/" . DB_NAME . " as " . DB_USER . "\n\n";

$db = getDB();

// List tables
echo "=== TABLES ===\n";
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo "  $t: $count rows\n";
}

// Show admin users (most sensitive)
echo "\n=== ADMIN USERS (first 10) ===\n";
foreach ($tables as $t) {
    if (stripos($t, 'user') !== false || stripos($t, 'admin') !== false || stripos($t, 'auth') !== false) {
        echo "--- $t ---\n";
        try {
            $rows = $db->query("SELECT * FROM `$t` LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
            }
        } catch (Exception $e) { echo "Error: " . $e->getMessage() . "\n"; }
    }
}

echo "\n=== END ===\n";
?>
