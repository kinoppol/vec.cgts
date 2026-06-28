-- ============================================================
-- Migration: เพิ่มตาราง sla_steps และคอลัมน์ใน case_events
-- วิธีใช้: รันผ่าน phpMyAdmin หรือ mysql CLI
-- ============================================================
USE vec_cgts;

-- 1. ตาราง sla_steps — ขั้นตอนและจำนวนวันที่กำหนด
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

-- 2. เพิ่มคอลัมน์ใน case_events
ALTER TABLE case_events
  ADD COLUMN IF NOT EXISTS step_key     VARCHAR(50) DEFAULT NULL AFTER sort_order,
  ADD COLUMN IF NOT EXISTS started_at   DATE        DEFAULT NULL AFTER step_key,
  ADD COLUMN IF NOT EXISTS completed_at DATE        DEFAULT NULL AFTER started_at;

-- 3. ข้อมูลเริ่มต้น 6 ขั้นตอน
INSERT INTO sla_steps (step_key, label, days_allowed, sort_order, note) VALUES
  ('receive',       'รับเรื่อง',              1,  10, 'นับจากวันที่ประชาชนยื่นเรื่อง'),
  ('propose_dir',   'เสนอ ผอ.สำนัก',         2,  20, 'เสนอผู้อำนวยการสำนักอำนวยการพิจารณา'),
  ('assign',        'มอบหมายนิติกร',           1,  30, 'มอบหมายเจ้าหน้าที่นิติกรเจ้าของเรื่อง'),
  ('investigate',   'ตรวจข้อเท็จจริง',        15, 40, 'นิติกรดำเนินการตรวจสอบและรวบรวมพยานหลักฐาน'),
  ('propose_boss',  'เสนอผู้บังคับบัญชา',     5,  50, 'เสนอสายบังคับบัญชาเพื่อพิจารณาสั่งการ'),
  ('order',         'ออกคำสั่ง',              3,  60, 'ออกหนังสือคำสั่ง/แจ้งผลการพิจารณา')
ON DUPLICATE KEY UPDATE
  label        = VALUES(label),
  days_allowed = VALUES(days_allowed),
  sort_order   = VALUES(sort_order),
  note         = VALUES(note);
