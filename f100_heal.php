<?php
header('Content-Type: text/plain; charset=utf-8');
$cfg='/var/www/vec.cgts/.git/config';
$orig=file_get_contents($cfg);
echo "=== ORIGINAL CONFIG ===\n{$orig}\n";
// restore https URL, drop broken sshCommand entirely
$c = "[core]\n\trepositoryformatversion = 0\n\tfilemode = true\n\tbare = false\n\tlogallrefupdates = true\n[remote \"origin\"]\n\turl = https://github.com/kinoppol/vec.cgts\n\tfetch = +refs/heads/*:refs/remotes/origin/*\n[branch \"main\"]\n\tremote = origin\n\tmerge = refs/heads/main\n";
file_put_contents($cfg,$c);
chmod($cfg,0755);
echo "=== NEW CONFIG ===\n".file_get_contents($cfg)."\n";
echo "=== verify git fetch works ===\n";
echo shell_exec('cd /var/www/vec.cgts && git fetch origin 2>&1; echo "fetch_exit=$?"');
echo shell_exec('cd /var/www/vec.cgts && git log --oneline -1 origin/main 2>&1');
echo shell_exec('cd /var/www/vec.cgts && git reset --hard origin/main 2>&1; echo "reset_exit=$?"');
echo shell_exec('cd /var/www/vec.cgts && git log --oneline -1 2>&1');
echo "=== done ===\n";
?>
