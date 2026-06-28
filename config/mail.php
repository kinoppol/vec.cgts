<?php
/**
 * config/mail.php — ตั้งค่าอีเมลและ token สำหรับ cron
 *
 * วิธีใช้งานอีเมลบน XAMPP:
 *   1. เปิด C:\xampp\php\php.ini
 *   2. ตั้ง smtp = smtp.gmail.com / smtp_port = 587
 *   3. ติดตั้ง Mercury Mail หรือใช้ sendmail ของ XAMPP
 *   (หรือ enable MAIL_ENABLED = false เพื่อปิดอีเมลชั่วคราว)
 */

define('MAIL_ENABLED',   false);                              // เปลี่ยนเป็น true เมื่อตั้งค่า SMTP แล้ว
define('MAIL_FROM',      'noreply@vec.cgts.th');
define('MAIL_FROM_NAME', 'ระบบรับเรื่องร้องเรียน สอศ.');
define('MAIL_CHARSET',   'UTF-8');

// token สำหรับเรียก cron ผ่าน HTTP GET /api/cron.php?token=xxx
// ควรเปลี่ยนเป็น random string ยาวๆ
define('CRON_TOKEN', 'cgts-cron-secret-2026');

/**
 * ส่งอีเมล HTML อย่างง่าย
 * คืนค่า true/false
 */
function sendMail(string $to, string $toName, string $subject, string $htmlBody): bool {
    if (!MAIL_ENABLED || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $fromName = mb_encode_mimeheader(MAIL_FROM_NAME, MAIL_CHARSET, 'B');
    $toLine   = mb_encode_mimeheader($toName, MAIL_CHARSET, 'B') . " <{$to}>";
    $subj     = mb_encode_mimeheader($subject, MAIL_CHARSET, 'B');
    $headers  = implode("\r\n", [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: base64",
        "From: {$fromName} <" . MAIL_FROM . ">",
        "Reply-To: " . MAIL_FROM,
        "X-Mailer: PHP/" . PHP_VERSION,
    ]);
    return mail($toLine, $subj, base64_encode($htmlBody), $headers);
}
