-- ระบบบริหารจัดการงานนิติการ สอศ.
-- MariaDB 10+ / MySQL 8+
-- UTF-8MB4

CREATE DATABASE IF NOT EXISTS vec_cgts
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE vec_cgts;

-- ----------------------------------------------------------------
-- officers: นิติกรและผู้รับผิดชอบสำนวน
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS officers (
  id         VARCHAR(10)  NOT NULL,
  name       VARCHAR(200) NOT NULL,
  job_title  VARCHAR(100) DEFAULT NULL,
  duty       VARCHAR(200) DEFAULT NULL,
  group_name VARCHAR(200) DEFAULT NULL,
  init       VARCHAR(10)  DEFAULT NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- users: บัญชีผู้ใช้งานระบบ (เจ้าหน้าที่ + ผู้บริหาร)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT          NOT NULL AUTO_INCREMENT,
  username      VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(200) NOT NULL,
  role             ENUM('officer','dir_legal','dir_admin','secretary','deputy_secretary','admin') NOT NULL DEFAULT 'officer',
  init             VARCHAR(10)  DEFAULT NULL,
  job_title        VARCHAR(200) DEFAULT NULL,
  group_name       VARCHAR(200) DEFAULT NULL,
  officer_id       VARCHAR(10)  DEFAULT NULL,
  active           TINYINT(1)   NOT NULL DEFAULT 1,
  can_manage_users TINYINT(1)   NOT NULL DEFAULT 0,
  avatar_path   VARCHAR(255) DEFAULT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  CONSTRAINT fk_user_officer FOREIGN KEY (officer_id) REFERENCES officers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- role_labels: ชื่อแสดงสำหรับบทบาท (ปรับแต่งได้)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_labels (
  role  VARCHAR(50)  NOT NULL,
  label VARCHAR(200) NOT NULL,
  PRIMARY KEY (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- cases: สำนวน/เรื่องร้องเรียน
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cases (
  id            VARCHAR(20)  NOT NULL,
  reg_number    VARCHAR(50)  DEFAULT '—',
  subject       TEXT         NOT NULL,
  track         ENUM('discipline','legal') NOT NULL,
  cat           VARCHAR(100) DEFAULT NULL,
  channel       VARCHAR(100) DEFAULT NULL,
  cls           ENUM('public','internal','restricted','secret') NOT NULL DEFAULT 'internal',
  status        ENUM('received','screening','rejected','case','assigned','investigating','reporting','closed')
                             NOT NULL DEFAULT 'received',
  priority      VARCHAR(20)  NOT NULL DEFAULT 'ปกติ',
  anon          TINYINT(1)   NOT NULL DEFAULT 0,
  complainant   VARCHAR(200) DEFAULT NULL,
  contact       VARCHAR(200) DEFAULT NULL,
  agency        VARCHAR(200) DEFAULT NULL,
  detail        TEXT         DEFAULT NULL,
  assignee_id   VARCHAR(10)  DEFAULT NULL,
  sla           ENUM('g','a','r') NOT NULL DEFAULT 'g',
  progress      TINYINT      NOT NULL DEFAULT 0,
  received_date DATE         DEFAULT NULL,
  due_date      DATE         DEFAULT NULL,
  created_by    INT          DEFAULT NULL,   -- user id ที่สร้าง (NULL = ประชาชน)
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_case_officer  FOREIGN KEY (assignee_id) REFERENCES officers (id) ON DELETE SET NULL,
  CONSTRAINT fk_case_creator  FOREIGN KEY (created_by)  REFERENCES users    (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- case_events: ไทม์ไลน์เหตุการณ์ของแต่ละสำนวน
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS case_events (
  id         INT          NOT NULL AUTO_INCREMENT,
  case_id    VARCHAR(20)  NOT NULL,
  title      VARCHAR(300) NOT NULL,
  actor      VARCHAR(100) DEFAULT NULL,
  moment     VARCHAR(100) DEFAULT NULL,
  detail     TEXT         DEFAULT NULL,
  ev_status  ENUM('done','active','pending') NOT NULL DEFAULT 'pending',
  icon       VARCHAR(50)  DEFAULT 'dot',
  sort_order SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_case_events_case (case_id),
  CONSTRAINT fk_event_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- case_files: ไฟล์แนบ
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS case_files (
  id           INT          NOT NULL AUTO_INCREMENT,
  case_id      VARCHAR(20)  NOT NULL,
  filename     VARCHAR(300) NOT NULL,
  stored_name  VARCHAR(300) DEFAULT NULL,
  size_label   VARCHAR(50)  DEFAULT NULL,
  cls          ENUM('public','internal','restricted','secret') NOT NULL DEFAULT 'internal',
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_case_files_case (case_id),
  CONSTRAINT fk_file_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- todo_items: รายการที่ต้องทำ (สำหรับ admin)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS todo_items (
  id           INT          NOT NULL AUTO_INCREMENT,
  user_id      INT          NOT NULL,
  title        VARCHAR(300) NOT NULL,
  detail       TEXT         DEFAULT NULL,
  done         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_todo_user (user_id),
  CONSTRAINT fk_todo_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- sla_settings: ตั้งค่าระยะเวลา SLA รายสายงาน/หมวด
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sla_settings (
  id          INT          NOT NULL AUTO_INCREMENT,
  track       ENUM('discipline','legal') NOT NULL,
  cat         VARCHAR(100) NOT NULL,
  days        INT          NOT NULL DEFAULT 30,
  note        VARCHAR(300) DEFAULT NULL,
  updated_by  INT          DEFAULT NULL,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sla_track_cat (track, cat),
  CONSTRAINT fk_sla_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- audit_log: บันทึกการเข้าถึง (PDPA compliance)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT          NOT NULL AUTO_INCREMENT,
  user_id    INT          DEFAULT NULL,
  action     VARCHAR(50)  NOT NULL,
  target_id  VARCHAR(50)  DEFAULT NULL,
  detail     TEXT         DEFAULT NULL,
  ip         VARCHAR(45)  DEFAULT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user   (user_id),
  KEY idx_audit_target (target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
