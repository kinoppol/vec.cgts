-- migration: เพิ่มคอลัมน์ภาพประจำตัวผู้ใช้
-- รันบน server ที่ติดตั้งแล้ว (ครั้งเดียว)

USE vec_cgts;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL;
