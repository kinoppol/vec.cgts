-- -------------------------------------------------------
-- personnel.sql — โครงสร้างบุคลากรกลุ่มนิติการ (จริง)
-- สร้างจาก flowcharts กลุ่มนิติกร ระบบร้องเรียน.pdf
-- -------------------------------------------------------
USE vec_cgts;

-- -------------------------------------------------------
-- officers — นิติกรและผู้รับผิดชอบสำนวน
-- -------------------------------------------------------
INSERT INTO officers (id, name, job_title, group_name, init) VALUES

-- กลุ่มงานกฎหมายและระเบียบ
('o1', 'นางสาวเยาวริดา พิณสายแก้ว', 'นิติกรชำนาญการพิเศษ',      'กลุ่มงานกฎหมายและระเบียบ', 'ยร'),
('o2', 'นายณวณ เจริญหลาย',           'นิติกรชำนาญการปฏิบัติการ', 'กลุ่มงานกฎหมายและระเบียบ', 'ณว'),
('o3', 'นายศิวกร เพชรสีเงิน',         'นิติกรชำนาญการปฏิบัติการ', 'กลุ่มงานกฎหมายและระเบียบ', 'ศก'),

-- กลุ่มงานวินัย
('o4', 'นางสาวภานิชา จันทราทิพย์',   'นิติกรชำนาญการปฏิบัติการ', 'กลุ่มงานวินัย',             'ภน'),
('o5', 'นางสาวจิดาภา ทองศรีสังข์',   'พนักงานบริหารทั่วไป',      'กลุ่มงานวินัย',             'จด'),
('o6', 'นางสาวกาญจนา อนันต์โก',      'พนักงานบริหารทั่วไป',      'กลุ่มงานวินัย',             'กญ'),
('o7', 'นางสาวโชติกา วิริยะจีระพิพัฒน์','พนักงานบริหารทั่วไป',   'กลุ่มงานวินัย',             'ชก')

ON DUPLICATE KEY UPDATE
  name      = VALUES(name),
  job_title = VALUES(job_title),
  group_name= VALUES(group_name),
  init      = VALUES(init);

-- -------------------------------------------------------
-- users — บัญชีผู้ใช้งานระบบ (รหัสผ่านจะถูก set โดย install.php)
-- -------------------------------------------------------
-- placeholder hash สำหรับ "password" — install.php จะ UPDATE ทับทั้งหมด
SET @ph = '$2y$12$K9qBp8lqXtbr6ek2PcCRSOoCQ3f9MFXqWqeGnxJLa7sVmNyqFwYxy';

-- ผอ.กลุ่มนิติการ
INSERT INTO users (username, password_hash, display_name, role, init, officer_id) VALUES
('wornwut', @ph, 'นายวรวุฒิ ดำขา', 'dir_legal', 'วว', NULL)
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), role=VALUES(role), init=VALUES(init);

-- ผอ.กลุ่มงานกฎหมายและระเบียบ + นิติกร
INSERT INTO users (username, password_hash, display_name, role, init, officer_id) VALUES
('yawrata',  @ph, 'นางสาวเยาวริดา พิณสายแก้ว',        'officer', 'ยร', 'o1'),
('nawan',    @ph, 'นายณวณ เจริญหลาย',                  'officer', 'ณว', 'o2'),
('siwakorn', @ph, 'นายศิวกร เพชรสีเงิน',               'officer', 'ศก', 'o3')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), officer_id=VALUES(officer_id), init=VALUES(init);

-- นิติกร กลุ่มงานวินัย
INSERT INTO users (username, password_hash, display_name, role, init, officer_id) VALUES
('panisa',   @ph, 'นางสาวภานิชา จันทราทิพย์',          'officer', 'ภน', 'o4'),
('jidapa',   @ph, 'นางสาวจิดาภา ทองศรีสังข์',          'officer', 'จด', 'o5'),
('kanjana',  @ph, 'นางสาวกาญจนา อนันต์โก',             'officer', 'กญ', 'o6'),
('chotika',  @ph, 'นางสาวโชติกา วิริยะจีระพิพัฒน์',    'officer', 'ชก', 'o7')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), officer_id=VALUES(officer_id), init=VALUES(init);

-- ผอ.สำนักอำนวยการ (ยังคงไว้ account เดิม แต่ปรับชื่อ)
UPDATE users SET display_name = 'ผู้อำนวยการสำนักอำนวยการ', init = 'ผอ' WHERE username = 'dir_admin';

-- -------------------------------------------------------
-- ปรับ display_name บัญชีทดสอบเดิม ให้สื่อความหมาย
-- -------------------------------------------------------
UPDATE users SET display_name = 'เจ้าหน้าที่นิติการ (ทดสอบ)', init = 'จน' WHERE username = 'officer';
UPDATE users SET display_name = 'นายวรวุฒิ ดำขา', init = 'วว'            WHERE username = 'dir_legal';
