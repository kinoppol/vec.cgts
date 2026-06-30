<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('vec_cgts_sess');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}

// เลขเวอร์ชันจากเวลา commit ล่าสุด รูปแบบ v0.yyMMddhhii
function appVersion(): ?string {
    // วิธีที่ 1: shell_exec (ใช้งานได้บนเซิร์ฟเวอร์จริง)
    if (function_exists('shell_exec')) {
        $out = @shell_exec('git -C ' . escapeshellarg(__DIR__) . ' log -1 --format=%ct 2>&1');
        if ($out !== null && ctype_digit(trim($out)))
            return 'v0.' . date('ymdHi', (int)trim($out));
    }
    // วิธีที่ 2: อ่านจาก .git/logs/HEAD โดยตรง (ทำงานได้แม้ shell_exec ถูก disable)
    $logFile = __DIR__ . '/.git/logs/HEAD';
    if (is_file($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $last  = end($lines);
        // format: <old> <new> <name> <email> <timestamp> <tz> <action> <msg>
        if ($last && preg_match('/>\s+(\d{10,})\s+[+-]\d{4}/', $last, $m))
            return 'v0.' . date('ymdHi', (int)$m[1]);
    }
    return null;
}

// ส่งข้อมูลผู้ใช้ปัจจุบันมาพร้อม HTML เพื่อลด round-trip
$initialUser = null;
if (!empty($_SESSION['user_id'])) {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, username, display_name, role, group_name, init, officer_id, can_manage_users, avatar_path FROM users WHERE id = ? AND active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $initialUser = $stmt->fetch() ?: null;
        if ($initialUser) {
            // ── รวบรวมบทบาทจากทุกแหล่ง แล้วเลือกบทบาทที่มีสิทธิ์สูงสุด ──
            //   (1) บทบาทส่วนตัว  (2) บทบาทของกลุ่มที่สังกัด (ทุกกลุ่ม)  (3) บทบาทหัวหน้ากลุ่มที่ได้รับแต่งตั้ง
            $ROLE_ORDER = ['officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin'];
            $candidates = [];
            if ($initialUser['role']) $candidates[] = $initialUser['role'];

            // (2) บทบาทของทุกกลุ่มที่ผู้ใช้สังกัด
            if (!empty($initialUser['group_name'])) {
                try {
                    $gr = $db->prepare("SELECT gr.role FROM group_roles gr JOIN groups g ON g.id = gr.group_id WHERE g.name = ?");
                    $gr->execute([$initialUser['group_name']]);
                    foreach ($gr->fetchAll(PDO::FETCH_COLUMN) as $r) { if ($r) $candidates[] = $r; }
                } catch (Throwable) {}
            }

            // (3) บทบาทหัวหน้ากลุ่ม (ถ้าได้รับแต่งตั้งเป็นหัวหน้ากลุ่มใด ๆ)
            $leaderGroup = null;
            try {
                $lg = $db->prepare('SELECT id, name, leader_role FROM groups WHERE leader_id = ? LIMIT 1');
                $lg->execute([$_SESSION['user_id']]);
                $leaderGroup = $lg->fetch() ?: null;
            } catch (Throwable) {
                // กรณียังไม่มีคอลัมน์ leader_role
                try {
                    $lg = $db->prepare('SELECT id, name FROM groups WHERE leader_id = ? LIMIT 1');
                    $lg->execute([$_SESSION['user_id']]);
                    $leaderGroup = $lg->fetch() ?: null;
                } catch (Throwable) { $leaderGroup = null; }
            }
            if ($leaderGroup && !empty($leaderGroup['leader_role'])) $candidates[] = $leaderGroup['leader_role'];

            // รายการบทบาททั้งหมด (ไม่ซ้ำ) เรียงสิทธิ์สูง→ต่ำ
            $roleList = array_values(array_unique(array_filter($candidates, fn($c) => in_array($c, $ROLE_ORDER, true))));
            usort($roleList, fn($a, $b) => array_search($b, $ROLE_ORDER, true) - array_search($a, $ROLE_ORDER, true));
            if (!$roleList) $roleList = ['officer'];

            // บทบาทที่ใช้งานอยู่: เคารพที่ผู้ใช้เลือกไว้ (active_role) ถ้ายังอยู่ในสิทธิ์ ไม่งั้นใช้สูงสุด
            $activeRole = (!empty($_SESSION['active_role']) && in_array($_SESSION['active_role'], $roleList, true))
                ? $_SESSION['active_role'] : $roleList[0];

            $initialUser['role']  = $activeRole;
            $initialUser['roles'] = $roleList;
            // sync session ให้ API ใช้ตรงกัน
            $_SESSION['role']  = $activeRole;
            $_SESSION['roles'] = $roleList;

            $initialUser['can_manage_users']  = (bool)($initialUser['can_manage_users'] ?? false);
            $initialUser['is_impersonating']  = !empty($_SESSION['impersonator_id']);
            $initialUser['leader_of_group']   = $leaderGroup ? ['id' => $leaderGroup['id'], 'name' => $leaderGroup['name']] : null;
            // กลุ่มทั้งหมดที่เป็นหัวหน้า + leader_role (ใช้แสดงชื่อกลุ่มต่อท้ายบทบาทในตัวสลับ)
            try {
                $lgs = $db->prepare('SELECT name, leader_role FROM groups WHERE leader_id = ?');
                $lgs->execute([$_SESSION['user_id']]);
                $initialUser['leader_groups'] = $lgs->fetchAll() ?: [];
            } catch (Throwable) { $initialUser['leader_groups'] = []; }
            if ($initialUser['is_impersonating']) {
                $initialUser['impersonator_id']   = (int)$_SESSION['impersonator_id'];
                $initialUser['impersonator_name'] = $_SESSION['impersonator_name'] ?? '';
            }
        }
    } catch (Throwable) {}
}

// โหลดชื่อบทบาทจาก DB (fallback เป็น {} ถ้าตารางยังไม่มี)
$initialRoleLabels = [];
try {
    $db   = getDB();
    $rows = $db->query('SELECT role, label FROM role_labels')->fetchAll();
    foreach ($rows as $r) $initialRoleLabels[$r['role']] = $r['label'];
} catch (Throwable) {}

// ── Daily notification check (รันหลัง response ส่งไปแล้ว ไม่ทำให้หน้าช้า) ──
if ($initialUser) {
    $lockFile = __DIR__ . '/data/notif_last_run.txt';
    $today    = date('Y-m-d');
    $lastRun  = is_file($lockFile) ? trim(file_get_contents($lockFile)) : '';
    if ($lastRun !== $today) {
        // บันทึกวันนี้ก่อน (ป้องกัน request คู่ขนานรัน 2 ครั้ง)
        @file_put_contents($lockFile, $today);
        // ลงทะเบียน shutdown function — รันหลัง response flush แล้ว
        register_shutdown_function(function () {
            // ปล่อย session lock ก่อนเพื่อไม่บล็อก request ถัดไป
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            // ignore abort เพื่อให้ทำงานต่อแม้ browser ปิด
            ignore_user_abort(true);
            // flush HTML ไปยัง client ก่อน
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                ob_end_flush(); flush();
            }
            // รัน cron logic (define flag ให้ cron.php รู้ว่ามาจาก shutdown)
            try {
                define('CGTS_CRON_FROM_SHUTDOWN', true);
                require_once __DIR__ . '/api/cron.php';
            } catch (Throwable) {}
        });
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ระบบบริหารงานนิติการ · สอศ.</title>
<link rel="icon" type="image/svg+xml" href="assets/ovec-logo.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div id="root"></div>
<!-- ข้อมูลผู้ใช้เริ่มต้น (server-injected) -->
<script>
window.__INITIAL_USER__  = <?= json_encode($initialUser,      JSON_UNESCAPED_UNICODE) ?>;
window.__ROLE_LABELS__   = <?= json_encode($initialRoleLabels, JSON_UNESCAPED_UNICODE) ?>;
window.__APP_BASE__      = <?= json_encode(rtrim(dirname($_SERVER['PHP_SELF']), '/\\'), JSON_UNESCAPED_UNICODE) ?>;
window.__APP_VERSION__   = <?= json_encode(appVersion(), JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js"
        integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L"
        crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js"
        integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm"
        crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js"
        integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y"
        crossorigin="anonymous"></script>

<?php
$jsxFiles = ['data','public','admin-officer','admin-directors','admin-users','admin-sla','admin-roles','admin-officers','admin-lookup','admin-exec','admin-case-tasks','admin-calendar','admin-groups','app'];
foreach ($jsxFiles as $f):
    $mt = filemtime(__DIR__ . "/{$f}.jsx");
?>
<script type="text/babel" src="<?= $f ?>.jsx?v=<?= $mt ?>"></script>
<?php endforeach; ?>
</body>
</html>
