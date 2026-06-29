-- migration: เพิ่มระบบปฏิทินกิจกรรม
USE vec_cgts;

CREATE TABLE IF NOT EXISTS calendar_events (
  id            INT          NOT NULL AUTO_INCREMENT,
  event_type    ENUM('meeting','court','investigation','document','committee') NOT NULL,
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
