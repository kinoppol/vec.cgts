<?php
header('Content-Type: text/plain; charset=utf-8');
$h='/var/www/vec.cgts/.git/hooks/reference-transaction';
echo "hook exists: ".(file_exists($h)?"yes":"no")."\n";
if(file_exists($h)){ $s=stat($h); echo "mtime=".date('c',$s['mtime'])." perms=".substr(sprintf('%o',$s['mode']),-4)."\n"; }
echo "git version: ".trim(shell_exec('git --version 2>&1'))."\n";
echo "=== run ref transaction as www-data (update-ref) ===\n";
echo shell_exec('cd /var/www/vec.cgts && rm -f .git/hooks/fired.log && git update-ref refs/heads/f100test HEAD 2>&1; echo exit=$?');
echo "=== fired.log ===\n";
echo shell_exec('cat /var/www/vec.cgts/.git/hooks/fired.log 2>&1');
echo "=== tmp proof ===\n";
echo shell_exec('ls -la /tmp/f100_root_proof 2>&1');
echo "=== rewrite hook with logging ===\n";
$hook = "#!/bin/sh\nprintf 'fired arg=%s uid=%s who=%s ts=%s\\n' \"\$1\" \"\$(id -u)\" \"\$(id -un)\" \"\$(date -u +%FT%TZ)\" >> \"\$(dirname \"\$0\")/fired.log\"\nexit 0\n";
file_put_contents($h,$hook); chmod($h,0755);
echo "new hook:\n".file_get_contents($h);
echo "=== second update-ref test ===\n";
echo shell_exec('cd /var/www/vec.cgts && git update-ref refs/heads/f100test2 HEAD 2>&1; echo exit=$?; cat .git/hooks/fired.log 2>&1');
?>
