-- migration: เพิ่มคอลัมน์ proposed_personnel ใน case_task_proposals
ALTER TABLE case_task_proposals
  ADD COLUMN proposed_personnel TEXT DEFAULT NULL AFTER proposed_groups;
