-- migration: เพิ่มคอลัมน์ proposed_groups ใน case_task_proposals
ALTER TABLE case_task_proposals
  ADD COLUMN proposed_groups TEXT DEFAULT NULL AFTER proposed_officer;
