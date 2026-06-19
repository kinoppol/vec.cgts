-- Migration: สร้างตาราง sla_settings และข้อมูลเริ่มต้น
-- รันบน vec_cgts ที่ติดตั้งแล้ว (ไม่กระทบข้อมูลเดิม)

USE vec_cgts;

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

INSERT INTO sla_settings (track, cat, days, note) VALUES
('discipline', 'งานร้องเรียน',          90, NULL),
('discipline', 'งานวินัย',             120, NULL),
('discipline', 'งานอุทธรณ์',            60, NULL),
('discipline', 'งานร้องทุกข์',          90, NULL),
('legal',      'ระเบียบ/กฎหมาย/คำสั่ง', 30, NULL),
('legal',      'นิติกรรมสัญญา',         30, NULL),
('legal',      'คดีปกครอง/แพ่ง/อาญา',  60, NULL),
('legal',      'ความรับผิดทางละเมิด',   90, NULL)
ON DUPLICATE KEY UPDATE days=VALUES(days);
