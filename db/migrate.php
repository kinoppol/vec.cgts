<?php
/**
 * migrate.php — migrations รวม
 *   [1] รัน personnel.sql และตั้งรหัสผ่านใหม่   ?confirm=run&pass=xxx
 *   [2] เพิ่มตาราง sla_steps + คอลัมน์ case_events  ?confirm=sla
 *   [3] เพิ่ม attachment columns ใน case_events        ?confirm=event_attach
 *   [4] ระบบแจ้งเตือน: notifications + notification_log + email ใน users  ?confirm=notifications
 */
require_once __DIR__ . '/../config/db.php';

$confirm = $_GET['confirm'] ?? '';

/* ── [4] Notification System ────────────────────────────── */
if ($confirm === 'notifications') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [4]: ระบบแจ้งเตือน</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // เพิ่มคอลัมน์ email ใน users
        $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('email', $cols)) {
            $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(200) DEFAULT NULL AFTER display_name");
            echo "✓ ALTER users ADD email\n";
        } else {
            echo "– users.email มีอยู่แล้ว ข้าม\n";
        }

        // สร้างตาราง notifications
        $db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id     INT NOT NULL,
              case_id     VARCHAR(20) NOT NULL,
              notif_type  VARCHAR(50) NOT NULL,
              title       VARCHAR(300) NOT NULL,
              body        TEXT DEFAULT NULL,
              read_at     DATETIME DEFAULT NULL,
              created_at  DATETIME NOT NULL DEFAULT NOW(),
              PRIMARY KEY (id),
              KEY idx_notif_user (user_id, read_at),
              KEY idx_notif_case (case_id),
              CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ CREATE TABLE notifications (IF NOT EXISTS)\n";

        // สร้างตาราง notification_log (ป้องกันส่งซ้ำ)
        $db->exec("
            CREATE TABLE IF NOT EXISTS notification_log (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              case_id     VARCHAR(20) NOT NULL,
              notif_type  VARCHAR(50) NOT NULL,
              user_id     INT NOT NULL,
              channel     VARCHAR(20) NOT NULL DEFAULT 'system',
              sent_at     DATETIME NOT NULL DEFAULT NOW(),
              PRIMARY KEY (id),
              KEY idx_nlog_lookup (case_id, notif_type, user_id, channel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ CREATE TABLE notification_log (IF NOT EXISTS)\n";

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
        echo "ขั้นตอนต่อไป:\n";
        echo "1. เพิ่มอีเมลให้ผู้ใช้ที่ หน้าจัดการผู้ใช้งาน\n";
        echo "2. ตั้งค่า config/mail.php (MAIL_ENABLED, SMTP)\n";
        echo "3. ตั้ง cron/Task Scheduler: php api/cron.php ทุกวัน 08:00 น.\n";
        echo "   หรือเรียกผ่าน URL: /api/cron.php?token=" . (defined('CRON_TOKEN') ? htmlspecialchars(CRON_TOKEN) : 'your-token') . "\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [3] Event Attachment columns ──────────────────────── */
if ($confirm === 'event_attach') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration: Event Attachment Columns</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $cols = $db->query("SHOW COLUMNS FROM case_events")->fetchAll(PDO::FETCH_COLUMN);
        foreach ([
            'attachment_name VARCHAR(300) DEFAULT NULL AFTER detail',
            'attachment_path VARCHAR(300) DEFAULT NULL AFTER attachment_name',
            'attachment_size VARCHAR(30)  DEFAULT NULL AFTER attachment_path',
        ] as $col) {
            $name = explode(' ', $col)[0];
            if (!in_array($name, $cols)) {
                $db->exec("ALTER TABLE case_events ADD COLUMN $col");
                echo "✓ ALTER case_events ADD $name\n";
            } else {
                echo "– $name มีอยู่แล้ว ข้าม\n";
            }
        }
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [2] SLA Steps migration ────────────────────────────── */
if ($confirm === 'sla') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration: SLA Steps</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("
            CREATE TABLE IF NOT EXISTS sla_steps (
              id           INT          NOT NULL AUTO_INCREMENT,
              step_key     VARCHAR(50)  NOT NULL,
              label        VARCHAR(200) NOT NULL,
              days_allowed INT          NOT NULL DEFAULT 1,
              sort_order   SMALLINT     NOT NULL DEFAULT 0,
              active       TINYINT(1)   NOT NULL DEFAULT 1,
              note         VARCHAR(300) DEFAULT NULL,
              updated_by   INT          DEFAULT NULL,
              updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_step_key (step_key),
              CONSTRAINT fk_slastep_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ CREATE TABLE sla_steps (IF NOT EXISTS)\n";

        $cols = $db->query("SHOW COLUMNS FROM case_events")->fetchAll(PDO::FETCH_COLUMN);

        foreach (['step_key VARCHAR(50) DEFAULT NULL AFTER sort_order',
                  'started_at DATE DEFAULT NULL AFTER step_key',
                  'completed_at DATE DEFAULT NULL AFTER started_at'] as $col) {
            $name = explode(' ', $col)[0];
            if (!in_array($name, $cols)) {
                $db->exec("ALTER TABLE case_events ADD COLUMN $col");
                echo "✓ ALTER case_events ADD $name\n";
            } else {
                echo "– $name มีอยู่แล้ว ข้าม\n";
            }
        }

        $db->exec("
            INSERT INTO sla_steps (step_key, label, days_allowed, sort_order, note) VALUES
              ('receive',      'รับเรื่อง',             1,  10, 'นับจากวันที่ประชาชนยื่นเรื่อง'),
              ('propose_dir',  'เสนอ ผอ.สำนัก',        2,  20, 'เสนอผู้อำนวยการสำนักอำนวยการพิจารณา'),
              ('assign',       'มอบหมายนิติกร',          1,  30, 'มอบหมายเจ้าหน้าที่นิติกรเจ้าของเรื่อง'),
              ('investigate',  'ตรวจข้อเท็จจริง',       15, 40, 'นิติกรดำเนินการตรวจสอบและรวบรวมพยานหลักฐาน'),
              ('propose_boss', 'เสนอผู้บังคับบัญชา',    5,  50, 'เสนอสายบังคับบัญชาเพื่อพิจารณาสั่งการ'),
              ('order',        'ออกคำสั่ง',             3,  60, 'ออกหนังสือคำสั่ง/แจ้งผลการพิจารณา')
            ON DUPLICATE KEY UPDATE
              label=VALUES(label), days_allowed=VALUES(days_allowed),
              sort_order=VALUES(sort_order), note=VALUES(note)
        ");
        $count = $db->query("SELECT COUNT(*) FROM sla_steps")->fetchColumn();
        echo "✓ INSERT/UPDATE sla_steps ($count ขั้นตอน)\n";
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [5] head_secretary role ────────────────────────────── */
if ($confirm === 'head_secretary') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [5]: บทบาทหัวหน้าธุรการ</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // ตรวจว่า head_secretary อยู่ใน ENUM แล้วหรือยัง
        $colInfo = $db->query("SHOW COLUMNS FROM users WHERE Field='role'")->fetch();
        if ($colInfo && strpos($colInfo['Type'], 'head_secretary') !== false) {
            echo "– users.role มี head_secretary อยู่แล้ว ข้าม\n";
        } else {
            $db->exec("ALTER TABLE users MODIFY role ENUM('officer','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin') NOT NULL DEFAULT 'officer'");
            echo "✓ ALTER users MODIFY role — เพิ่ม head_secretary\n";
        }

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [10] sub_category + channel seed ─────────────────────── */
if ($confirm === 'channel_lookup') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [10]: ช่องทางรับเรื่อง (lookup)</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // เพิ่ม sub_category column
        $cols = $db->query("SHOW COLUMNS FROM lookup_items")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('sub_category', $cols)) {
            $db->exec("ALTER TABLE lookup_items ADD COLUMN sub_category VARCHAR(100) DEFAULT NULL AFTER category");
            echo "✓ ALTER lookup_items ADD sub_category\n";
        } else {
            echo "– sub_category มีอยู่แล้ว ข้าม\n";
        }

        // seed channel_type
        $types = ['องค์กรอิสระ','ศาล','ภายใน','เอกชน'];
        foreach ($types as $i => $t) {
            $ex = $db->prepare("SELECT id FROM lookup_items WHERE category='channel_type' AND name=?")->execute([$t]);
            $ex = $db->query("SELECT id FROM lookup_items WHERE category='channel_type' AND name=" . $db->quote($t))->fetch();
            if (!$ex) {
                $db->prepare("INSERT INTO lookup_items (category, name, sort_order) VALUES ('channel_type',?,?)")->execute([$t, ($i+1)*10]);
                echo "✓ INSERT channel_type: $t\n";
            } else {
                echo "– channel_type '$t' มีอยู่แล้ว ข้าม\n";
            }
        }

        // seed channel_item
        $items = [
            ['องค์กรอิสระ','ป.ป.ช.',10],
            ['องค์กรอิสระ','ป.ป.ท.',20],
            ['องค์กรอิสระ','ป.ป.ง.',30],
            ['องค์กรอิสระ','สตง.',40],
            ['องค์กรอิสระ','สมาชิกวุฒิสภา',50],
            ['องค์กรอิสระ','ศูนย์ดำรงธรรม',60],
            ['องค์กรอิสระ','คณะกรรมการสิทธิมนุษยชนแห่งชาติ',70],
            ['องค์กรอิสระ','สำนักนายกรัฐมนตรี',80],
            ['องค์กรอิสระ','ศาลากลาง',90],
            ['องค์กรอิสระ','สำนักตรวจการแผ่นดิน',100],
            ['องค์กรอิสระ','องค์การปกครองท้องถิ่น',110],
            ['องค์กรอิสระ','สำนักงานตำรวจแห่งชาติ',120],
            ['องค์กรอิสระ','การไฟฟ้า',130],
            ['องค์กรอิสระ','การประปา',140],
            ['องค์กรอิสระ','รัฐสภา',150],
            ['องค์กรอิสระ','อื่น ๆ',999],
            ['ศาล','ศาลปกครอง',10],
            ['ศาล','ศาลแพ่ง',20],
            ['ศาล','ศาลอาญา',30],
            ['ศาล','ศาลเยาวชนและครอบครัว',40],
            ['ศาล','อนุญาโตตุลาการ',50],
            ['ศาล','อื่น ๆ',999],
            ['ภายใน','สป.ศธ.',10],
            ['ภายใน','รมว.ศธ.',20],
            ['ภายใน','สถาบันอาชีวศึกษา',30],
            ['ภายใน','กรรมการวิทยาลัย',40],
            ['ภายใน','วิทยาลัย',50],
            ['ภายใน','กลุ่มงานจริยธรรม',60],
            ['ภายใน','คุรุสภา',70],
            ['ภายใน','ก.ค.ศ.',80],
            ['ภายใน','อื่น ๆ',999],
            ['เอกชน','สื่อออนไลน์',10],
            ['เอกชน','อื่น ๆ',999],
        ];
        foreach ($items as [$sub, $name, $ord]) {
            $ex = $db->query("SELECT id FROM lookup_items WHERE category='channel_item' AND sub_category=" . $db->quote($sub) . " AND name=" . $db->quote($name))->fetch();
            if (!$ex) {
                $db->prepare("INSERT INTO lookup_items (category, sub_category, name, sort_order) VALUES ('channel_item',?,?,?)")->execute([$sub, $name, $ord]);
                echo "✓ INSERT channel_item [$sub] $name\n";
            } else {
                echo "– [$sub] $name มีอยู่แล้ว ข้าม\n";
            }
        }

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [9] เพิ่ม general ใน track ENUM ──────────────────────── */
if ($confirm === 'track_general') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [9]: track ENUM — บริหารงานทั่วไป</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach (['cases', 'sla_settings'] as $tbl) {
            $col = $db->query("SHOW COLUMNS FROM `$tbl` WHERE Field='track'")->fetch();
            if ($col && strpos($col['Type'], "'general'") !== false) {
                echo "– $tbl.track มี general อยู่แล้ว ข้าม\n";
            } else {
                $db->exec("ALTER TABLE `$tbl` MODIFY track ENUM('discipline','legal','general') NOT NULL");
                echo "✓ ALTER $tbl MODIFY track — เพิ่ม general\n";
            }
        }

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [8] สร้างตาราง case_task_proposals (ถ้ายังไม่มี) ────── */
if ($confirm === 'proposals_table') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [8]: ตาราง case_task_proposals</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("
            CREATE TABLE IF NOT EXISTS case_task_proposals (
              id               INT          NOT NULL AUTO_INCREMENT,
              case_id          VARCHAR(20)  NOT NULL,
              from_task_no     TINYINT      NOT NULL,
              to_task_no       TINYINT      NOT NULL,
              proposed_officer VARCHAR(10)  DEFAULT NULL,
              proposed_groups  TEXT         DEFAULT NULL,
              proposed_personnel TEXT       DEFAULT NULL,
              proposed_by      INT          NOT NULL,
              propose_note     TEXT         DEFAULT NULL,
              next_due_date    DATE         DEFAULT NULL,
              status           ENUM('pending','approved','changed') NOT NULL DEFAULT 'pending',
              final_officer    VARCHAR(10)  DEFAULT NULL,
              reviewed_by      INT          DEFAULT NULL,
              review_note      TEXT         DEFAULT NULL,
              created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              reviewed_at      TIMESTAMP    NULL DEFAULT NULL,
              PRIMARY KEY (id),
              KEY idx_ctp_case (case_id),
              CONSTRAINT fk_ctp_case     FOREIGN KEY (case_id)          REFERENCES cases    (id) ON DELETE CASCADE,
              CONSTRAINT fk_ctp_prop_off FOREIGN KEY (proposed_officer) REFERENCES officers (id) ON DELETE SET NULL,
              CONSTRAINT fk_ctp_fin_off  FOREIGN KEY (final_officer)    REFERENCES officers (id) ON DELETE SET NULL,
              CONSTRAINT fk_ctp_prop_by  FOREIGN KEY (proposed_by)      REFERENCES users    (id) ON DELETE CASCADE,
              CONSTRAINT fk_ctp_rev_by   FOREIGN KEY (reviewed_by)      REFERENCES users    (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ CREATE TABLE case_task_proposals (IF NOT EXISTS)\n";

        // เพิ่มคอลัมน์เสริม (กรณีตารางเก่าที่ไม่มี 2 คอลัมน์นี้)
        $cols = $db->query("SHOW COLUMNS FROM case_task_proposals")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('proposed_groups', $cols)) {
            $db->exec("ALTER TABLE case_task_proposals ADD COLUMN proposed_groups TEXT DEFAULT NULL AFTER proposed_officer");
            echo "✓ ALTER case_task_proposals ADD proposed_groups\n";
        } else {
            echo "– proposed_groups มีอยู่แล้ว ข้าม\n";
        }
        if (!in_array('proposed_personnel', $cols)) {
            $db->exec("ALTER TABLE case_task_proposals ADD COLUMN proposed_personnel TEXT DEFAULT NULL AFTER proposed_groups");
            echo "✓ ALTER case_task_proposals ADD proposed_personnel\n";
        } else {
            echo "– proposed_personnel มีอยู่แล้ว ข้าม\n";
        }

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [6] Proposal groups column ────────────────────────── */
if ($confirm === 'proposal_groups') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [6]: proposed_groups column</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $cols = $db->query("SHOW COLUMNS FROM case_task_proposals")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('proposed_groups', $cols)) {
            $db->exec("ALTER TABLE case_task_proposals ADD COLUMN proposed_groups TEXT DEFAULT NULL AFTER proposed_officer");
            echo "✓ ALTER case_task_proposals ADD proposed_groups\n";
        } else {
            echo "– proposed_groups มีอยู่แล้ว ข้าม\n";
        }

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [7] Proposal personnel column ─────────────────────── */
if ($confirm === 'proposal_personnel') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [7]: proposed_personnel column</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $cols = $db->query("SHOW COLUMNS FROM case_task_proposals")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('proposed_personnel', $cols)) {
            $db->exec("ALTER TABLE case_task_proposals ADD COLUMN proposed_personnel TEXT DEFAULT NULL AFTER proposed_groups");
            echo "✓ ALTER case_task_proposals ADD proposed_personnel\n";
        } else {
            echo "– proposed_personnel มีอยู่แล้ว ข้าม\n";
        }

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [14] group_roles — บทบาทของกลุ่ม ───────────────────── */
if ($confirm === 'group_roles') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [14]: ตาราง group_roles</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("CREATE TABLE IF NOT EXISTS group_roles (
            group_id INT NOT NULL,
            role     VARCHAR(50) NOT NULL,
            PRIMARY KEY (group_id, role),
            CONSTRAINT fk_grole_group FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ CREATE TABLE group_roles\n";
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [13] groups table — ตารางกลุ่ม ─────────────────────── */
if ($confirm === 'groups_table') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [13]: ตาราง groups</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $db->exec("CREATE TABLE IF NOT EXISTS groups (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            leader_id  INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_group_name (name),
            KEY idx_group_leader (leader_id),
            CONSTRAINT fk_group_leader FOREIGN KEY (leader_id) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ CREATE TABLE groups\n";

        // seed จากชื่อกลุ่มที่มีอยู่ใน users.group_name
        $existing = $db->query("SELECT DISTINCT group_name FROM users WHERE group_name IS NOT NULL AND group_name != ''")->fetchAll(PDO::FETCH_COLUMN);
        $ins = $db->prepare("INSERT IGNORE INTO groups (name) VALUES (?)");
        foreach ($existing as $gname) { $ins->execute([$gname]); echo "✓ seed กลุ่ม: $gname\n"; }

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [16] leader_role — บทบาทเฉพาะหัวหน้ากลุ่ม ─────────── */
if ($confirm === 'leader_role') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [16]: groups.leader_role</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $cols = $db->query("SHOW COLUMNS FROM groups")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('leader_role', $cols)) {
            $db->exec("ALTER TABLE groups ADD COLUMN leader_role VARCHAR(50) DEFAULT NULL AFTER leader_id");
            echo "✓ ALTER groups ADD leader_role\n";
        } else {
            echo "– groups.leader_role มีอยู่แล้ว ข้าม\n";
        }
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [15] nullable role — ให้ users.role เป็น NULL ได้ ── */
if ($confirm === 'nullable_role') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [15]: users.role nullable (ไม่กำหนดบทบาท)</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("ALTER TABLE users MODIFY role ENUM('officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin') DEFAULT NULL");
        echo "✓ ALTER users.role → nullable\n";
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [12] clerk role — เพิ่มบทบาทธุรการ ───────────────── */
if ($confirm === 'clerk_role') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [12]: บทบาทธุรการ (clerk)</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // แก้ไข role ที่ไม่อยู่ใน ENUM ใหม่ก่อน ALTER (ป้องกัน "Data truncated")
        $valid = ['officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin'];
        $in    = implode(',', array_map(fn($v) => "'$v'", $valid));
        $fixed = $db->exec("UPDATE users SET role='officer' WHERE role IS NULL OR role NOT IN ($in)");
        if ($fixed > 0) echo "✓ แก้ไข $fixed แถวที่มี role ไม่ถูกต้อง → officer\n";

        $db->exec("ALTER TABLE users MODIFY role ENUM('officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin') NOT NULL DEFAULT 'officer'");
        echo "✓ ALTER users.role ENUM เพิ่ม clerk\n";

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [11] cls ENUM — เปลี่ยนชั้นความลับ ───────────────── */
if ($confirm === 'cls_enum') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [11]: cls ENUM (ชั้นความลับ)</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // แปลงค่าเดิม → ค่าใหม่ก่อน ALTER
        $db->exec("UPDATE cases SET cls='public'  WHERE cls IN ('internal')");
        $db->exec("UPDATE cases SET cls='secret'  WHERE cls IN ('restricted')");
        $db->exec("UPDATE case_files SET cls='public' WHERE cls IN ('internal')");
        $db->exec("UPDATE case_files SET cls='secret' WHERE cls IN ('restricted')");
        echo "✓ UPDATE ค่าเดิม internal→public, restricted→secret\n";

        $db->exec("ALTER TABLE cases MODIFY cls ENUM('public','secret','topsecret','classified') NOT NULL DEFAULT 'public'");
        echo "✓ ALTER cases.cls ENUM\n";

        $db->exec("ALTER TABLE case_files MODIFY cls ENUM('public','secret','topsecret','classified') NOT NULL DEFAULT 'public'");
        echo "✓ ALTER case_files.cls ENUM\n";

        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [18] backfill SLA started_at สำหรับเรื่องเก่า ─────────── */
if ($confirm === 'backfill_sla') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [18]: Backfill SLA started_at เรื่องเก่า</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // ── ขั้น receive: ทุกเรื่อง ← received_date หรือ created_at ──
        $cases = $db->query("SELECT id, received_date, created_at FROM cases")->fetchAll();
        $receiveStep = $db->query("SELECT * FROM sla_steps WHERE step_key='receive' AND active=1")->fetch();
        $r1 = 0; $r2 = 0;
        foreach ($cases as $c) {
            $startDate = $c['received_date'] ?: date('Y-m-d', strtotime($c['created_at']));
            // UPDATE ถ้ามี event อยู่แล้ว (started_at NULL)
            $upd = $db->prepare("UPDATE case_events SET started_at=?, ev_status='active' WHERE case_id=? AND step_key='receive' AND started_at IS NULL");
            $upd->execute([$startDate, $c['id']]);
            if ($upd->rowCount() > 0) { $r1++; continue; }
            // ตรวจว่ามี event อยู่แล้วหรือยัง
            $ex = $db->prepare("SELECT id FROM case_events WHERE case_id=? AND step_key='receive'");
            $ex->execute([$c['id']]);
            if ($ex->fetch()) continue;
            // INSERT ใหม่
            if ($receiveStep) {
                $db->prepare("INSERT INTO case_events (case_id,title,ev_status,icon,sort_order,step_key,started_at) VALUES (?,?,'active','inbox',?,?,?)")
                   ->execute([$c['id'], $receiveStep['label'], (int)$receiveStep['sort_order'], 'receive', $startDate]);
                $r2++;
            }
        }
        echo "✓ receive: UPDATE $r1 แถว, INSERT $r2 แถว\n";

        // ── ขั้น propose_dir: เรื่องที่มี proposal from_task_no=0 ──
        $hasPropTable = $db->query("SHOW TABLES LIKE 'case_task_proposals'")->fetchColumn();
        if (!$hasPropTable) { echo "– ไม่มีตาราง case_task_proposals ข้าม propose_dir + assign\n"; goto done; }
        $propStep = $db->query("SELECT * FROM sla_steps WHERE step_key='propose_dir' AND active=1")->fetch();
        $proposals = $db->query(
            "SELECT case_id, MIN(created_at) AS t
             FROM case_task_proposals WHERE from_task_no=0
             GROUP BY case_id"
        )->fetchAll();
        $p1 = 0; $p2 = 0;
        foreach ($proposals as $p) {
            $t = date('Y-m-d', strtotime($p['t']));
            $upd = $db->prepare("UPDATE case_events SET started_at=COALESCE(started_at,?), ev_status='active' WHERE case_id=? AND step_key='propose_dir' AND started_at IS NULL");
            $upd->execute([$t, $p['case_id']]);
            if ($upd->rowCount() > 0) { $p1++; continue; }
            $ex = $db->prepare("SELECT id FROM case_events WHERE case_id=? AND step_key='propose_dir'");
            $ex->execute([$p['case_id']]);
            if ($ex->fetch()) continue;
            if ($propStep) {
                $db->prepare("INSERT INTO case_events (case_id,title,ev_status,icon,sort_order,step_key,started_at) VALUES (?,?,'active','flag',?,?,?)")
                   ->execute([$p['case_id'], $propStep['label'], (int)$propStep['sort_order'], 'propose_dir', $t]);
                $p2++;
            }
        }
        echo "✓ propose_dir: UPDATE $p1 แถว, INSERT $p2 แถว\n";

        // ── ขั้น assign: เรื่องที่ proposal ถูก approve/change แล้ว ──
        $assignStep = $db->query("SELECT * FROM sla_steps WHERE step_key='assign' AND active=1")->fetch();
        $approved = $db->query(
            "SELECT case_id, MIN(COALESCE(reviewed_at, created_at)) AS t
             FROM case_task_proposals WHERE from_task_no=0 AND status IN ('approved','changed')
             GROUP BY case_id"
        )->fetchAll();
        $a1 = 0; $a2 = 0;
        foreach ($approved as $a) {
            $t = date('Y-m-d', strtotime($a['t']));
            $upd = $db->prepare("UPDATE case_events SET started_at=COALESCE(started_at,?), ev_status='active' WHERE case_id=? AND step_key='assign' AND started_at IS NULL");
            $upd->execute([$t, $a['case_id']]);
            if ($upd->rowCount() > 0) { $a1++; continue; }
            $ex = $db->prepare("SELECT id FROM case_events WHERE case_id=? AND step_key='assign'");
            $ex->execute([$a['case_id']]);
            if ($ex->fetch()) continue;
            if ($assignStep) {
                $db->prepare("INSERT INTO case_events (case_id,title,ev_status,icon,sort_order,step_key,started_at) VALUES (?,?,'active','gavel',?,?,?)")
                   ->execute([$a['case_id'], $assignStep['label'], (int)$assignStep['sort_order'], 'assign', $t]);
                $a2++;
            }
        }
        echo "✓ assign: UPDATE $a1 แถว, INSERT $a2 แถว\n";

        done:
        echo "\n<span class='ok'>✅ Backfill สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [19] app_settings — ตาราง key-value การตั้งค่าระบบ ────── */
if ($confirm === 'app_settings') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [19]: App Settings</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
            `key`   VARCHAR(100) NOT NULL,
            `value` TEXT         DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "✓ สร้างตาราง app_settings\n";
        $db->exec("INSERT IGNORE INTO app_settings (`key`,`value`) VALUES ('case_id_prefix','CMP')");
        echo "✓ seed case_id_prefix = CMP\n";
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [17] dept_name — กลุ่มงาน (สายงาน) ย้ายจาก officers มาไว้ที่ groups ── */
if ($confirm === 'dept_name') {
    echo '<style>body{font-family:sans-serif;padding:24px}pre{background:#f5f5f5;padding:16px;border-radius:6px}.ok{color:green}.err{color:red}</style>';
    echo '<h2>Migration [17]: groups.dept_name — กลุ่มงาน (สายงาน)</h2><pre>';
    try {
        $db = getDB();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $cols = $db->query("SHOW COLUMNS FROM groups")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('dept_name', $cols)) {
            $db->exec("ALTER TABLE groups ADD COLUMN dept_name VARCHAR(200) DEFAULT NULL AFTER leader_role");
            echo "✓ ALTER groups ADD dept_name\n";
        } else {
            echo "– groups.dept_name มีอยู่แล้ว ข้าม\n";
        }
        echo "\n<span class='ok'>✅ Migration สำเร็จ</span>\n";
    } catch (Throwable $e) {
        echo "<span class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo '</pre>';
    exit;
}

/* ── [1] Personnel + password (เดิม) ────────────────────── */
// ป้องกันการเรียกโดยไม่ตั้งใจ
if ($confirm !== 'run') {
    echo '<style>body{font-family:sans-serif;padding:24px}code{background:#f5f5f5;padding:2px 6px;border-radius:4px}li{margin:8px 0}</style>';
    echo '<h2>Migration</h2><ul>';
    echo '<li><b>[1] Personnel + รหัสผ่าน</b><br><code><a href="?confirm=run&pass=password">migrate.php?confirm=run&pass=password</a></code></li>';
    echo '<li><b>[2] SLA Steps</b> — เพิ่มตาราง sla_steps และคอลัมน์ case_events<br><code><a href="?confirm=sla">migrate.php?confirm=sla</a></code></li>';
    echo '<li><b>[3] Event Attachment</b> — เพิ่มคอลัมน์ attachment_name/path/size ใน case_events<br><code><a href="?confirm=event_attach">migrate.php?confirm=event_attach</a></code></li>';
    echo '<li><b>[4] ระบบแจ้งเตือน</b> — notifications, notification_log, users.email<br><code><a href="?confirm=notifications">migrate.php?confirm=notifications</a></code></li>';
    echo '<li><b>[5] หัวหน้าธุรการ</b> — เพิ่ม head_secretary ใน users.role ENUM<br><code><a href="?confirm=head_secretary">migrate.php?confirm=head_secretary</a></code></li>';
    echo '<li><b>[8] ตาราง case_task_proposals</b> — สร้างตาราง (รวม proposed_groups + proposed_personnel)<br><code><a href="?confirm=proposals_table">migrate.php?confirm=proposals_table</a></code></li>';
    echo '<li><b>[9] สายงานบริหารงานทั่วไป</b> — เพิ่ม general ใน track ENUM ของ cases + sla_settings<br><code><a href="?confirm=track_general">migrate.php?confirm=track_general</a></code></li>';
    echo '<li><b>[10] ช่องทางรับเรื่อง</b> — เพิ่ม sub_category ใน lookup_items + seed ประเภทหน่วยงาน 4 ประเภท<br><code><a href="?confirm=channel_lookup">migrate.php?confirm=channel_lookup</a></code></li>';
    echo '<li><b>[11] ชั้นความลับ</b> — เปลี่ยน cls ENUM: public/secret/topsecret/classified<br><code><a href="?confirm=cls_enum">migrate.php?confirm=cls_enum</a></code></li>';
    echo '<li><b>[12] บทบาทธุรการ</b> — เพิ่ม clerk ใน users.role ENUM<br><code><a href="?confirm=clerk_role">migrate.php?confirm=clerk_role</a></code></li>';
    echo '<li><b>[13] ตารางกลุ่ม</b> — สร้าง groups table + seed จาก users.group_name<br><code><a href="?confirm=groups_table">migrate.php?confirm=groups_table</a></code></li>';
    echo '<li><b>[14] บทบาทของกลุ่ม</b> — สร้าง group_roles table<br><code><a href="?confirm=group_roles">migrate.php?confirm=group_roles</a></code></li>';
    echo '<li><b>[15] ไม่กำหนดบทบาท</b> — ให้ users.role เป็น NULL ได้ (ยึดบทบาทจากกลุ่ม)<br><code><a href="?confirm=nullable_role">migrate.php?confirm=nullable_role</a></code></li>';
    echo '<li><b>[16] บทบาทหัวหน้ากลุ่ม</b> — เพิ่ม groups.leader_role สำหรับบทบาทเฉพาะหัวหน้า<br><code><a href="?confirm=leader_role">migrate.php?confirm=leader_role</a></code></li>';
    echo '<li><b>[17] กลุ่มงาน (สายงาน)</b> — เพิ่ม groups.dept_name แทนการกำหนดรายบุคคลใน officers<br><code><a href="?confirm=dept_name">migrate.php?confirm=dept_name</a></code></li>';
    echo '<li><b>[18] Backfill SLA</b> — เติม started_at ให้เรื่องเก่า (receive / propose_dir / assign)<br><code><a href="?confirm=backfill_sla">migrate.php?confirm=backfill_sla</a></code></li>';
    echo '<li><b>[19] App Settings</b> — สร้างตาราง app_settings สำหรับการตั้งค่าระบบ (prefix รหัสเรื่อง ฯลฯ)<br><code><a href="?confirm=app_settings">migrate.php?confirm=app_settings</a></code></li>';
    echo '<li><b>[6] กลุ่มงานที่เสนอ</b> — เพิ่มคอลัมน์ proposed_groups ใน case_task_proposals<br><code><a href="?confirm=proposal_groups">migrate.php?confirm=proposal_groups</a></code></li>';
    echo '<li><b>[7] บุคลากรที่เกี่ยวข้อง</b> — เพิ่มคอลัมน์ proposed_personnel ใน case_task_proposals<br><code><a href="?confirm=proposal_personnel">migrate.php?confirm=proposal_personnel</a></code></li>';
    echo '</ul>';
    exit;
}

$newPass = $_GET['pass'] ?? '';
if ($newPass === '') {
    die('ต้องระบุ ?pass=รหัสผ่าน');
}

try {
    $db = getDB();

    // รัน personnel.sql
    $sql = file_get_contents(__DIR__ . '/personnel.sql');
    // ลบ comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    $sql = preg_replace('/--[^\n]*/', '', $sql);

    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    $ran = 0;
    foreach ($stmts as $stmt) {
        if (preg_match('/^\s*USE\s+/i', $stmt)) continue;
        $db->exec($stmt);
        $ran++;
    }

    // ตั้งรหัสผ่านทุก account
    $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $affected = $db->prepare('UPDATE users SET password_hash = ?')->execute([$hash]);

    // แสดงผล users ทั้งหมด
    $users = $db->query('SELECT username, display_name, role, active FROM users ORDER BY role, username')->fetchAll();

    echo '<style>body{font-family:sans-serif;padding:24px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:8px 12px;text-align:left}th{background:#f5f5f5}.ok{color:green}.err{color:red}</style>';
    echo '<h2 class="ok">✓ Migration สำเร็จ</h2>';
    echo "<p>รัน {$ran} statements จาก personnel.sql</p>";
    echo "<p>ตั้งรหัสผ่าน <b>" . htmlspecialchars($newPass) . "</b> ให้ทุกบัญชีแล้ว</p>";
    echo '<table><tr><th>username</th><th>display_name</th><th>role</th><th>active</th></tr>';
    foreach ($users as $u) {
        echo "<tr><td><code>{$u['username']}</code></td><td>{$u['display_name']}</td><td>{$u['role']}</td><td>" . ($u['active'] ? '✓' : '✗') . "</td></tr>";
    }
    echo '</table>';
    echo '<p style="color:red"><b>⚠ ลบไฟล์นี้ออกหลังใช้งาน:</b> <code>db/migrate.php</code></p>';

} catch (Throwable $e) {
    echo '<p style="color:red">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
