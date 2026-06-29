-- migration: เพิ่ม sla_deadline ใน calendar_events.event_type
ALTER TABLE calendar_events
  MODIFY event_type
    ENUM('meeting','court','investigation','document','committee','sla_deadline') NOT NULL;
