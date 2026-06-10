<?php
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('vec_cgts_sess');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}

// ส่งข้อมูลผู้ใช้ปัจจุบันมาพร้อม HTML เพื่อลด round-trip
$initialUser = null;
if (!empty($_SESSION['user_id'])) {
    try {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, username, display_name, role, init FROM users WHERE id = ? AND active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        $initialUser = $stmt->fetch() ?: null;
    } catch (Throwable) {}
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ระบบรับเรื่องร้องเรียน–ร้องทุกข์ · สอศ.</title>
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
window.__INITIAL_USER__ = <?= json_encode($initialUser, JSON_UNESCAPED_UNICODE) ?>;
window.__APP_BASE__ = <?= json_encode(rtrim(dirname($_SERVER['PHP_SELF']), '/\\'), JSON_UNESCAPED_UNICODE) ?>;
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
$jsxFiles = ['data','public','admin-officer','admin-directors','admin-users','app'];
foreach ($jsxFiles as $f):
    $mt = filemtime(__DIR__ . "/{$f}.jsx");
?>
<script type="text/babel" src="<?= $f ?>.jsx?v=<?= $mt ?>"></script>
<?php endforeach; ?>
</body>
</html>
