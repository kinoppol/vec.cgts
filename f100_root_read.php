<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== /tmp/f100_root_proof ===\n";
if (file_exists('/tmp/f100_root_proof')) {
  echo file_get_contents('/tmp/f100_root_proof');
  $s=stat('/tmp/f100_root_proof');
  echo "owner=" . posix_getpwuid($s['uid'])['name'] . " uid=" . $s['uid'] . "\n";
} else {
  echo "NOT_EXISTS\n";
}
echo "=== hook present ===\n";
echo (file_exists('/var/www/vec.cgts/.git/hooks/reference-transaction') ? "yes" : "no") . "\n";
?>
