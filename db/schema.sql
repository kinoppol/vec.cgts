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
  role             ENUM('officer','clerk','head_secretary','dir_legal','dir_admin','secretary','deputy_secretary','admin') DEFAULT NULL,
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
-- lookup_items: รายการอ้างอิง (กลุ่มงาน / ตำแหน่ง) สำหรับ dropdown
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lookup_items (
  id           INT          NOT NULL AUTO_INCREMENT,
  category     VARCHAR(50)  NOT NULL,  -- 'group_name' | 'job_title' | 'channel_type' | 'channel_item'
  sub_category VARCHAR(100) DEFAULT NULL,
  name         VARCHAR(200) NOT NULL,
  sort_order   SMALLINT     NOT NULL DEFAULT 0,
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_lookup_cat (category, active)
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
  track         ENUM('discipline','legal','general') NOT NULL,
  cat           VARCHAR(100) DEFAULT NULL,
  channel       VARCHAR(100) DEFAULT NULL,
  cls           ENUM('public','secret','topsecret','classified') NOT NULL DEFAULT 'public',
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
  id           INT          NOT NULL AUTO_INCREMENT,
  case_id      VARCHAR(20)  NOT NULL,
  title        VARCHAR(300) NOT NULL,
  actor        VARCHAR(100) DEFAULT NULL,
  moment       VARCHAR(100) DEFAULT NULL,
  detail       TEXT         DEFAULT NULL,
  ev_status    ENUM('done','active','pending') NOT NULL DEFAULT 'pending',
  icon         VARCHAR(50)  DEFAULT 'dot',
  sort_order   SMALLINT     NOT NULL DEFAULT 0,
  step_key     VARCHAR(50)  DEFAULT NULL,
  started_at   DATE         DEFAULT NULL,
  completed_at DATE         DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_case_events_case (case_id),
  CONSTRAINT fk_event_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- sla_steps: กรอบเวลาตามขั้นตอนการดำเนินงาน
-- ----------------------------------------------------------------
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
  cls          ENUM('public','secret','topsecret','classified') NOT NULL DEFAULT 'public',
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
  track       ENUM('discipline','legal','general') NOT NULL,
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

-- ----------------------------------------------------------------
-- case_tasks: งานย่อย 5 ขั้นตอนของแต่ละสำนวน
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS case_tasks (
  id           INT          NOT NULL AUTO_INCREMENT,
  case_id      VARCHAR(20)  NOT NULL,
  task_no      TINYINT      NOT NULL,
  task_name    VARCHAR(200) NOT NULL,
  officer_id   VARCHAR(10)  DEFAULT NULL,
  start_date   DATE         DEFAULT NULL,
  due_date     DATE         DEFAULT NULL,
  status       ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  progress     TINYINT      NOT NULL DEFAULT 0,
  note         TEXT         DEFAULT NULL,
  completed_at TIMESTAMP    NULL DEFAULT NULL,
  completed_by INT          DEFAULT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_case_tasks_case (case_id),
  CONSTRAINT fk_ctask_case    FOREIGN KEY (case_id)      REFERENCES cases    (id) ON DELETE CASCADE,
  CONSTRAINT fk_ctask_officer FOREIGN KEY (officer_id)   REFERENCES officers (id) ON DELETE SET NULL,
  CONSTRAINT fk_ctask_user    FOREIGN KEY (completed_by) REFERENCES users    (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- case_task_proposals: การเสนอมอบหมายงานขั้นตอนถัดไป
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS case_task_proposals (
  id               INT          NOT NULL AUTO_INCREMENT,
  case_id          VARCHAR(20)  NOT NULL,
  from_task_no     TINYINT      NOT NULL,
  to_task_no       TINYINT      NOT NULL,
  proposed_officer   VARCHAR(10)  DEFAULT NULL,
  proposed_groups    TEXT         DEFAULT NULL,
  proposed_personnel TEXT         DEFAULT NULL,
  proposed_by        INT          NOT NULL,
  propose_note       TEXT         DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- calendar_events: กิจกรรมในปฏิทิน (ประชุม, นัดศาล, ฯลฯ)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS calendar_events (
  id            INT          NOT NULL AUTO_INCREMENT,
  event_type    ENUM('meeting','court','investigation','document','committee','sla_deadline') NOT NULL,
  title         VARCHAR(300) NOT NULL,
  event_date    DATE         NOT NULL,
  start_time    TIME         DEFAULT NULL,
  end_time      TIME         DEFAULT NULL,
  case_id       VARCHAR(20)  DEFAULT NULL,
  officer_id    VARCHAR(10)  DEFAULT NULL,
  created_by    INT          NOT NULL,
  note          TEXT         DEFAULT NULL,
  notif_3_sent  TINYINT(1)   NOT NULL DEFAULT 0,
  notif_7_sent  TINYINT(1)   NOT NULL DEFAULT 0,
  notif_over_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cal_date (event_date),
  CONSTRAINT fk_cal_case    FOREIGN KEY (case_id)    REFERENCES cases    (id) ON DELETE SET NULL,
  CONSTRAINT fk_cal_officer FOREIGN KEY (officer_id) REFERENCES officers (id) ON DELETE SET NULL,
  CONSTRAINT fk_cal_user    FOREIGN KEY (created_by) REFERENCES users    (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------
-- groups: กลุ่มผู้ใช้งาน
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS groups (
  id          INT          NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  leader_id   INT          DEFAULT NULL,
  leader_role VARCHAR(50)  DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_group_name (name),
  KEY idx_group_leader (leader_id),
  CONSTRAINT fk_group_leader FOREIGN KEY (leader_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_roles (
  group_id INT         NOT NULL,
  role     VARCHAR(50) NOT NULL,
  PRIMARY KEY (group_id, role),
  CONSTRAINT fk_grole_group FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
