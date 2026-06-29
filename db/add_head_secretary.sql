-- migration: เพิ่มบทบาท head_secretary (หัวหน้าธุรการ)
ALTER TABLE users
  MODIFY role ENUM('officer','dir_legal','dir_admin','secretary','deputy_secretary','admin','head_secretary')
  NOT NULL DEFAULT 'officer';
