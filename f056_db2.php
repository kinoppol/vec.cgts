<?php
// F056 Database access - verbose
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=vec_cgts;charset=utf8mb4', 'adminer', 'cgts2026!', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected OK\n";
    
    // Show databases
    echo "\n=== DATABASES ===\n";
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dbs as $d) echo "  $d\n";
    
    // Show tables in vec_cgts
    echo "\n=== TABLES in vec_cgts ===\n";
    $tables = $pdo->query("SHOW TABLES FROM vec_cgts")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) === 0) {
        echo "  (no tables)\n";
    } else {
        foreach ($tables as $t) {
            $count = $pdo->query("SELECT COUNT(*) FROM vec_cgts.`$t`")->fetchColumn();
            echo "  $t: $count rows\n";
        }
    }
    
    // Show all databases' tables
    echo "\n=== ALL DATABASES TABLES ===\n";
    foreach ($dbs as $d) {
        if (in_array($d, ['information_schema', 'mysql', 'performance_schema', 'sys'])) continue;
        echo "--- $d ---\n";
        try {
            $ts = $pdo->query("SHOW TABLES FROM `$d`")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ts as $t) {
                $count = $pdo->query("SELECT COUNT(*) FROM `$d`.`$t`")->fetchColumn();
                echo "  $t: $count rows\n";
            }
        } catch (Exception $e) { echo "  Error: " . $e->getMessage() . "\n"; }
    }
    
    // Dump admin/user tables
    echo "\n=== ADMIN/USER TABLES ===\n";
    foreach ($tables as $t) {
        if (preg_match('/user|admin|auth|login|account|staff|officer/i', $t)) {
            echo "--- $t ---\n";
            $rows = $pdo->query("SELECT * FROM vec_cgts.`$t` LIMIT 5")->fetchAll();
            foreach ($rows as $r) echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>
