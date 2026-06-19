-- Migration: เพิ่ม role เลขาธิการ และ รองเลขาธิการ สอศ.
-- รันบน vec_cgts ที่ติดตั้งแล้ว (ไม่กระทบข้อมูลเดิม)

USE vec_cgts;

-- เพิ่ม ENUM values ใหม่
ALTER TABLE users
  MODIFY COLUMN role
    ENUM('officer','dir_legal','dir_admin','secretary','deputy_secretary','admin')
    NOT NULL DEFAULT 'officer';

-- เพิ่มผู้ใช้ตัวอย่าง (password = "password" — เปลี่ยนก่อนใช้จริง)
INSERT INTO users (username, password_hash, display_name, role, init, officer_id, can_manage_users)
VALUES
  ('yospol',     '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายยศพล เวณุโกเศศ',            'secretary',        'ยศ', NULL, 0),
  ('withawat',   '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายวิทวัส ปัญจมะวัต',          'deputy_secretary', 'วท', NULL, 0),
  ('sanga',      '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายสง่า แต่เชื้อสาย',          'deputy_secretary', 'สง', NULL, 0),
  ('narongchai', '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'นายณรงค์ชัย เจริญรุจิทรัพย์',  'deputy_secretary', 'ณช', NULL, 0)
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), role=VALUES(role), init=VALUES(init);
