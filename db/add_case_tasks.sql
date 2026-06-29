-- migration: เพิ่มระบบงานย่อย (case_tasks + case_task_proposals)
-- รันบน server ที่ติดตั้งแล้วครั้งเดียว

USE vec_cgts;

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

CREATE TABLE IF NOT EXISTS case_task_proposals (
  id               INT          NOT NULL AUTO_INCREMENT,
  case_id          VARCHAR(20)  NOT NULL,
  from_task_no     TINYINT      NOT NULL,
  to_task_no       TINYINT      NOT NULL,
  proposed_officer VARCHAR(10)  DEFAULT NULL,
  proposed_by      INT          NOT NULL,
  propose_note     TEXT         DEFAULT NULL,
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
