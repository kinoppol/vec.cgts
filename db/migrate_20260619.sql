-- =============================================================
-- Migration 2026-06-19: todo_items + secretary/deputy_secretary
-- สำหรับ vec_cgts ที่ติดตั้งไว้ก่อนหน้า (ไม่กระทบข้อมูลเดิม)
-- รัน: mysql -u root vec_cgts < db/migrate_20260619.sql
-- =============================================================

USE vec_cgts;

-- -------------------------------------------------------------
-- 1. ตาราง todo_items (รายการที่ต้องทำ สำหรับ admin)
-- -------------------------------------------------------------
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

-- -------------------------------------------------------------
-- 2. เพิ่ม ENUM role: secretary และ deputy_secretary
-- -------------------------------------------------------------
ALTER TABLE users
  MODIFY COLUMN role
    ENUM('officer','dir_legal','dir_admin','secretary','deputy_secretary','admin')
    NOT NULL DEFAULT 'officer';

-- -------------------------------------------------------------
-- 3. เพิ่มผู้ใช้ เลขาธิการ และ รองเลขาธิการ สอศ.
--    (password = "password" — กรุณาเปลี่ยนก่อนใช้งานจริง)
-- -------------------------------------------------------------
INSERT INTO users (username, password_hash, display_name, role, init, officer_id, can_manage_users)
VALUES
  ('yospol',     '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายยศพล เวณุโกเศศ',           'secretary',        'ยศ', NULL, 0),
  ('withawat',   '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายวิทวัส ปัญจมะวัต',         'deputy_secretary', 'วท', NULL, 0),
  ('sanga',      '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายสง่า แต่เชื้อสาย',         'deputy_secretary', 'สง', NULL, 0),
  ('narongchai', '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายณรงค์ชัย เจริญรุจิทรัพย์', 'deputy_secretary', 'ณช', NULL, 0)
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), role=VALUES(role), init=VALUES(init);
