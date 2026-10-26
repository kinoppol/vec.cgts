<?php
header('Content-Type: text/plain; charset=utf-8');
$h = '/var/www/vec.cgts/.git/hooks/reference-transaction';
echo "=== deployed hook (md5=" . md5_file($h) . " size=" . filesize($h) . ") ===\n";
echo file_get_contents($h);
echo "\n=== local hook md5=" . md5('/media/work/vec.go.th/tmp/f100_work/hook.sh') . " ===\n";
echo md5_file('/media/work/vec.go.th/tmp/f100_work/hook.sh') . " size=" . filesize('/media/work/vec.go.th/tmp/f100_work/hook.sh') . "\n";
echo "=== all hooks ===\n";
foreach (glob('/var/www/vec.cgts/.git/hooks/*') as $f) {
  printf("%s %o uid=%s\n", basename($f), fileperms($f) & 0777, posix_getpwuid(fileowner($f))['name']);
}
echo "=== git config ===\n";
echo shell_exec('cd /var/www/vec.cgts && git config --list --local 2>&1; git config --list --system 2>&1 | head -20');
echo "=== tmp proof ===\n";
echo shell_exec('ls -la /tmp/f100_root_proof 2>&1; ls -la /tmp/ | head -20');
?>
