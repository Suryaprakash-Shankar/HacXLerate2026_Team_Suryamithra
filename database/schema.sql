-- SURYAMITHRA Database Schema
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS audit_logs, notifications, interventions, jobs, assignments, feedback, student_skills, role_skills, job_roles,
  placement, engagement, lms_activity, period_attendance, attendance, academic_records, subject_marks, class_assignments, departments, students, users, roles;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE roles (
  id TINYINT PRIMARY KEY AUTO_INCREMENT,
  code VARCHAR(20) UNIQUE NOT NULL,
  label VARCHAR(60) NOT NULL
);
INSERT INTO roles (code,label) VALUES 
  ('admin','Administrator'),
  ('hod','HOD / Head of Dept'),
  ('faculty','Teacher / Faculty'),
  ('placement','Placement Officer'),
  ('student','Student');

CREATE TABLE departments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  code VARCHAR(20) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT IGNORE INTO departments (name, code) VALUES 
  ('Aeronautical Engineering', 'AERO'),
  ('Agricultural Engineering', 'AGRI'),
  ('Artificial Intelligence and Data Science', 'AIDS'),
  ('Biomedical Engineering', 'BME'),
  ('Biotechnology', 'BT'),
  ('Chemical Engineering', 'CHEM'),
  ('Civil Engineering', 'CIVIL'),
  ('Computer Science and Engineering', 'CSE'),
  ('CSE - Artificial Intelligence and Machine Learning', 'AIML'),
  ('CSE - Internet of Things', 'IOT'),
  ('Cyber Security', 'CYBER'),
  ('Electrical and Electronics Engineering', 'EEE'),
  ('Electronics and Communication Engineering', 'ECE'),
  ('Food Technology', 'FT'),
  ('Information Technology', 'IT'),
  ('Mechanical Engineering', 'MECH'),
  ('Mechatronics Engineering', 'MCT'),
  ('Pharmaceutical Technology', 'PT'),
  ('Robotics and Automation', 'RA');

CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL,
  department VARCHAR(60) NULL,
  added_by INT NULL,
  firebase_uid VARCHAR(128) NULL,
  avatar VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

INSERT IGNORE INTO users (id, name, email, password_hash, role, department, added_by) VALUES
(1, 'Dr. Meenakshi Rao', 'admin@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'admin', 'Management', NULL),
(2, 'Dr. Aravind Kumar', 'hod_aero@suryamithra.local', '$2y$10$ViF6O6NXxWaxcs6nz5I0V..GFJu4HnwwjXnKxyV3CX5U4m.VXRl.C', 'hod', 'AERO', 1),
(3, 'Dr. Meena Krishnan', 'hod_agri@suryamithra.local', '$2y$10$j1GGaX5kdEjITA9cvt3uEeNcO9tY/u307/JLHJnB3B2923KYM/8aa', 'hod', 'AGRI', 1),
(4, 'Dr. Karthik Raj', 'hod_aids@suryamithra.local', '$2y$10$/MV..6qjXMoJHfxSbV/n9OZGfgJ9srDnnrH9ufAP7FK8Uy6UenDj2', 'hod', 'AIDS', 1),
(5, 'Dr. Priya Natarajan', 'hod_bme@suryamithra.local', '$2y$10$7Vi5/uuLYONy695HKT8nMuSph86Cnu/D9VWtoEPqfW9yhQVkgD2sO', 'hod', 'BME', 1),
(6, 'Dr. S. Kavitha', 'hod_bt@suryamithra.local', '$2y$10$3gGJD5y.mea8y9Q6yJ3.huEW8F0SqWN9WwYHoncASvIw4/aZ55kze', 'hod', 'BT', 1),
(7, 'Dr. V. Raghavan', 'hod_chem@suryamithra.local', '$2y$10$Ud33kwRT734ZPtzeuQ.Qauh2eiCUVWQkhdANzjxC/NqW.d3sJyZaS', 'hod', 'CHEM', 1),
(8, 'Dr. Arun Prakash', 'hod_civil@suryamithra.local', '$2y$10$XROq3YKe24fp6cc4m4KWiebU6PIeQOnPECrlr3T0duebybcenf8hq', 'hod', 'CIVIL', 1),
(9, 'Dr. Suresh Babu', 'hod_cse@suryamithra.local', '$2y$10$jAn.Ql/IOTIAlw.la1OmEeDiYBgVkHoNlS/0lQt0W0vE9qRc0hQzm', 'hod', 'CSE', 1),
(10, 'Dr. P. Srinivas', 'hod_aiml@suryamithra.local', '$2y$10$pROeNOiDQBq1V51sPHhhX.WlauDeg0DS2t8En2Ev32VYw63kVVvYS', 'hod', 'AIML', 1),
(11, 'Dr. M. Surya', 'hod_iot@suryamithra.local', '$2y$10$yTOTSMin2Ea4EI.0GtvlK.sNj/Q9ld6ar33Y2X5ZLJwlMI.hiJd86', 'hod', 'IOT', 1),
(12, 'Dr. Naveen Kumar', 'hod_cyber@suryamithra.local', '$2y$10$.tmeoCkaMoNnJObRyKdhBOp9gX.tOG4NoMMa7mzk7X2OOIUKviKce', 'hod', 'CYBER', 1),
(13, 'Dr. R. Mahendran', 'hod_eee@suryamithra.local', '$2y$10$e.A.OMpxhfFSSx/5ZRgKfewVn6sJ6oEJjH5KSRBJi60kKKwzkWiPW', 'hod', 'EEE', 1),
(14, 'Dr. Anitha Devi', 'hod_ece@suryamithra.local', '$2y$10$M7ggS471s9aNoTkDu2cV3eyvikOhimlh2XrYMtqhHhOzRYUc3U5Z6', 'hod', 'ECE', 1),
(15, 'Dr. Gokul Raj', 'hod_ft@suryamithra.local', '$2y$10$IyXn.g7pg/6O/9065gkr8OMgt3KfyXyTxhKDPqHt.sVOkmFxGZdw6', 'hod', 'FT', 1),
(16, 'Dr. S. Pradeep', 'hod_it@suryamithra.local', '$2y$10$rpa0CcLMBRIukqX6Y.UELO9jqL0/A6LY/Zfm5cRUkSSkZa/PT3bme', 'hod', 'IT', 1),
(17, 'Dr. Ramesh Kumar', 'hod_mech@suryamithra.local', '$2y$10$PGPqhlBtY7waJCw2NWuLPuGw/lnft9vN0TVzACpEczhea/SflvHgK', 'hod', 'MECH', 1),
(18, 'Dr. Lakshmi Narayanan', 'hod_mct@suryamithra.local', '$2y$10$IMATIGaTn8/VFFNqEqIiBOybTet7exy/YIhvXLgNIp0LcA.G0yT8u', 'hod', 'MCT', 1),
(19, 'Dr. Divya Mohan', 'hod_pt@suryamithra.local', '$2y$10$GUYcE3qjhFCM732vvWEvOOBPmdjLHikLdX9fqvOxdFRVG7DW13KzS', 'hod', 'PT', 1),
(20, 'Dr. Vignesh Kumar', 'hod_ra@suryamithra.local', '$2y$10$DNRX4oCBRXpJi1FwLHEtTOfaUr3sjc3fy9UFcpd8DjT1jVi50QU96', 'hod', 'RA', 1),
(21, 'Prof. Karthik Subramanian', 'faculty@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'CSE', 9),
(22, 'Prof. Sunita Rao', 'teacher_cse2@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'CSE', 9),
(23, 'Prof. R. Anand', 'teacher_aero@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'AERO', 2),
(24, 'Prof. S. Malathi', 'teacher_agri@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'AGRI', 3),
(25, 'Prof. K. Venkatesh', 'teacher_aids@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'AIDS', 4),
(26, 'Prof. G. Shalini', 'teacher_aiml@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'AIML', 10),
(27, 'Prof. N. Balaji', 'teacher_bme@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'BME', 5),
(28, 'Prof. R. Deepa', 'teacher_bt@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'BT', 6),
(29, 'Prof. M. Selvam', 'teacher_chem@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'CHEM', 7),
(30, 'Prof. T. Vijay', 'teacher_civil@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'CIVIL', 8),
(31, 'Prof. P. Harini', 'teacher_cyber@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'CYBER', 12),
(32, 'Prof. V. Rajesh', 'teacher_ece@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'ECE', 14),
(33, 'Prof. S. Jayanti', 'teacher_eee@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'EEE', 13),
(34, 'Prof. K. Mohan', 'teacher_ft@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'FT', 15),
(35, 'Prof. A. Dinesh', 'teacher_iot@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'IOT', 11),
(36, 'Prof. Amit Varma', 'teacher_it@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'IT', 16),
(37, 'Prof. B. Saravanan', 'teacher_mct@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'MCT', 18),
(38, 'Prof. C. Ganesh', 'teacher_mech@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'MECH', 17),
(39, 'Prof. D. Swathi', 'teacher_pt@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'PT', 19),
(40, 'Prof. E. Murali', 'teacher_ra@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'faculty', 'RA', 20),
(41, 'Divya Nair', 'placement@suryamithra.local', '$2y$10$nGKmeJddsfu0WxUnPbNdieyEnL0oIe0hkZJHyPcHj1cD1MUdqPC3e', 'placement', 'Placement Cell', 1);

CREATE TABLE job_roles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) UNIQUE NOT NULL
);

INSERT IGNORE INTO job_roles (id, name) VALUES
  (1, 'Java Backend Developer'),
  (2, 'Data Analyst'),
  (3, 'Full Stack Developer'),
  (4, 'Cloud & DevOps Engineer');

CREATE TABLE role_skills (
  role_id INT NOT NULL,
  skill VARCHAR(60) NOT NULL,
  required TINYINT NOT NULL,
  PRIMARY KEY (role_id, skill),
  FOREIGN KEY (role_id) REFERENCES job_roles(id) ON DELETE CASCADE
);

INSERT IGNORE INTO role_skills (role_id, skill, required) VALUES
  (1, 'Java', 65), (1, 'SQL', 60), (1, 'Spring Boot', 65), (1, 'REST API', 60), (1, 'Git', 55), (1, 'Problem Solving', 60),
  (2, 'SQL', 65), (2, 'Python', 60), (2, 'Excel', 65), (2, 'Statistics', 60), (2, 'Power BI', 55), (2, 'Communication', 65),
  (3, 'JavaScript', 65), (3, 'React', 60), (3, 'Node.js', 60), (3, 'SQL', 55), (3, 'Git', 55), (3, 'Teamwork', 60),
  (4, 'Linux', 65), (4, 'Docker', 60), (4, 'AWS', 60), (4, 'Git', 55), (4, 'Python', 50), (4, 'Problem Solving', 65);

CREATE TABLE students (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  teacher_id INT NULL,
  student_code VARCHAR(20) UNIQUE NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NULL,
  department VARCHAR(60) NOT NULL,
  class_name VARCHAR(30) NOT NULL DEFAULT 'Class A',
  semester TINYINT NOT NULL DEFAULT 5,
  bio TEXT NULL,
  interests VARCHAR(255) NULL,
  projects TEXT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE class_assignments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  department VARCHAR(60) NOT NULL,
  class_name VARCHAR(30) NOT NULL,
  teacher_id INT NOT NULL,
  academic_year VARCHAR(20) DEFAULT '2025-2026',
  UNIQUE KEY (department, class_name),
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT IGNORE INTO class_assignments (department, class_name, teacher_id) VALUES
  ('AERO', 'Class A', 23),
  ('AGRI', 'Class A', 24),
  ('AIDS', 'Class A', 25),
  ('AIML', 'Class A', 26),
  ('BME', 'Class A', 27),
  ('BT', 'Class A', 28),
  ('CHEM', 'Class A', 29),
  ('CIVIL', 'Class A', 30),
  ('CSE', 'Class A', 21),
  ('CSE', 'Class B', 22),
  ('CYBER', 'Class A', 31),
  ('ECE', 'Class A', 32),
  ('EEE', 'Class A', 33),
  ('FT', 'Class A', 34),
  ('IOT', 'Class A', 35),
  ('IT', 'Class A', 36),
  ('MCT', 'Class A', 37),
  ('MECH', 'Class A', 38),
  ('PT', 'Class A', 39),
  ('RA', 'Class A', 40);

CREATE TABLE subject_marks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  subject_code VARCHAR(20) NOT NULL,
  subject_name VARCHAR(100) NOT NULL,
  staff_id INT NULL,
  internal_mark DECIMAL(5,2) DEFAULT 0,
  exam_mark DECIMAL(5,2) DEFAULT 0,
  total_pct DECIMAL(5,2) DEFAULT 0,
  grade VARCHAR(5) DEFAULT 'A',
  semester TINYINT DEFAULT 5,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE academic_records (
  student_id INT PRIMARY KEY,
  cgpa DECIMAL(4,2) NOT NULL,
  backlogs TINYINT NOT NULL DEFAULT 0,
  internal_avg DECIMAL(5,2) NOT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE attendance (
  student_id INT PRIMARY KEY,
  overall_pct DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS period_attendance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  subject_code VARCHAR(20) NOT NULL DEFAULT 'GEN',
  staff_id INT NULL,
  date DATE NOT NULL,
  period_number TINYINT NOT NULL DEFAULT 1,
  status ENUM('Present','Absent','Late','OD') DEFAULT 'Present',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY (student_id, date, period_number),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS assignments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  department VARCHAR(60) NOT NULL,
  class_name VARCHAR(30) NOT NULL DEFAULT 'Class A',
  year TINYINT NOT NULL DEFAULT 3,
  semester TINYINT NOT NULL DEFAULT 5,
  subject_code VARCHAR(20) NOT NULL,
  created_by INT NOT NULL,
  due_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE lms_activity (
  student_id INT PRIMARY KEY,
  login_freq INT NOT NULL,
  assignment_completion DECIMAL(5,2) NOT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE engagement (
  student_id INT PRIMARY KEY,
  events INT DEFAULT 0,
  clubs INT DEFAULT 0,
  hackathons INT DEFAULT 0,
  certifications INT DEFAULT 0,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE placement (
  student_id INT PRIMARY KEY,
  aptitude DECIMAL(5,2),
  coding DECIMAL(5,2),
  mock_interview DECIMAL(5,2),
  resume DECIMAL(5,2),
  applications INT DEFAULT 0,
  role_id INT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES job_roles(id) ON DELETE SET NULL
);

CREATE TABLE student_skills (
  student_id INT NOT NULL,
  skill VARCHAR(60) NOT NULL,
  level TINYINT NOT NULL,
  category ENUM('technical','soft') DEFAULT 'technical',
  PRIMARY KEY (student_id, skill),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE feedback (
  student_id INT PRIMARY KEY,
  satisfaction DECIMAL(3,1) NOT NULL,
  faculty_rating DECIMAL(3,1) NOT NULL,
  notes TEXT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE interventions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  type VARCHAR(80) NOT NULL,
  mentor VARCHAR(100) NOT NULL,
  priority ENUM('High','Medium','Low') DEFAULT 'Medium',
  status ENUM('Planned','In Progress','Completed') DEFAULT 'Planned',
  outcome VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE jobs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  company VARCHAR(100) NOT NULL,
  title VARCHAR(120) NOT NULL,
  role_id INT NULL,
  min_readiness TINYINT DEFAULT 60,
  package_lpa DECIMAL(5,2) NULL,
  deadline DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES job_roles(id) ON DELETE SET NULL
);

CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  type VARCHAR(20) DEFAULT 'info',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NULL,
  action VARCHAR(80) NOT NULL,
  detail VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
