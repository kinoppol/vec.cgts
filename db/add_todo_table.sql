-- Migration: สร้างตาราง todo_items สำหรับระบบรายการที่ต้องทำ
-- รันบน vec_cgts ที่ติดตั้งแล้ว (ไม่กระทบข้อมูลเดิม)

USE vec_cgts;

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
