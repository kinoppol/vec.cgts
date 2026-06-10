-- migration: เพิ่ม admin role และ can_manage_users column
-- รันบน server ที่ติดตั้งแล้ว (ครั้งเดียว)

USE vec_cgts;

-- เพิ่ม column (ถ้ายังไม่มี)
ALTER TABLE users
  MODIFY COLUMN role ENUM('officer','dir_legal','dir_admin','admin') NOT NULL DEFAULT 'officer';

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS can_manage_users TINYINT(1) NOT NULL DEFAULT 0;

-- เพิ่มบัญชี admin (ถ้ายังไม่มี)
-- รหัสผ่านเดิมจะถูก UPDATE ทับโดย install.php หรือ migrate.php
INSERT INTO users (username, password_hash, display_name, role, init, can_manage_users)
VALUES ('admin', '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy', 'ผู้ดูแลระบบ', 'admin', 'AD', 1)
ON DUPLICATE KEY UPDATE role='admin', can_manage_users=1;
