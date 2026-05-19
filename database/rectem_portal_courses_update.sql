-- =========================
-- RECTEM PORTAL COURSE / STRUCTURE UPDATE
-- =========================

ALTER TABLE students
ADD COLUMN IF NOT EXISTS current_session VARCHAR(20) DEFAULT '2024/2025' AFTER semester;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_role VARCHAR(20) NOT NULL,
    actor_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS result_upload_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    department_id INT NOT NULL,
    level VARCHAR(20) NOT NULL,
    session VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    total_rows INT DEFAULT 0,
    matched_rows INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS portal_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(100) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO portal_settings (setting_key, setting_value) VALUES
('course_registration_status', 'closed'),
('consultation_status', 'closed'),
('max_units_nd1', '32'),
('max_units_nd2', '34'),
('max_units_hnd1', '36'),
('max_units_hnd2', '36')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT IGNORE INTO departments (department_name) VALUES
('Computer Science'),
('Science Laboratory Technology'),
('Electrical/Electronics Engineering'),
('Business Administration'),
('Accountancy'),
('Civil Engineering'),
('Architectural Technology'),
('Estate Management & Valuation');

-- Static department admin accounts
INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'CSC001', 'Computer Science Admin', 'cscadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Computer Science';

INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'SLT001', 'SLT Admin', 'sltadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Science Laboratory Technology';

INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'EEE001', 'Electrical Admin', 'eeeadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Electrical/Electronics Engineering';

INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'BAM001', 'Business Admin', 'bamadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Business Administration';

INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'ACC001', 'Accountancy Admin', 'accadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Accountancy';

INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'CVE001', 'Civil Engineering Admin', 'cveadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Civil Engineering';

INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'ARC001', 'Architecture Admin', 'arcadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Architectural Technology';

INSERT IGNORE INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'ESM001', 'Estate Management Admin', 'esmadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Estate Management & Valuation';

-- Demo official student records
INSERT IGNORE INTO student_records (matric_number, full_name, department_id, level)
SELECT 'R2024/620/014', 'ADEBOWALE ADEYINKA JOSIAH', id, 'ND1'
FROM departments WHERE department_name = 'Computer Science';

INSERT IGNORE INTO student_records (matric_number, full_name, department_id, level)
SELECT 'R2024/620/001', 'MURITALA ILERI SIAMBIAT', id, 'ND1'
FROM departments WHERE department_name = 'Computer Science';

INSERT IGNORE INTO student_records (matric_number, full_name, department_id, level)
SELECT 'R2024/620/002', 'OJUADE DEBORAH', id, 'ND1'
FROM departments WHERE department_name = 'Computer Science';

INSERT IGNORE INTO student_records (matric_number, full_name, department_id, level)
SELECT 'R2024/620/003', 'JIMOH OLUWADAMILARE', id, 'ND1'
FROM departments WHERE department_name = 'Computer Science';

INSERT IGNORE INTO student_records (matric_number, full_name, department_id, level)
SELECT 'R2024/620/004', 'GEORGE THEHILAH', id, 'ND1'
FROM departments WHERE department_name = 'Computer Science';

-- Ensure courses table exists
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL,
    course_title VARCHAR(150) NOT NULL,
    unit INT NOT NULL,
    department_id INT NOT NULL,
    level VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Remove old Computer Science ND1/ND2 demo courses to avoid duplicates
DELETE c FROM courses c
JOIN departments d ON c.department_id = d.id
WHERE d.department_name = 'Computer Science'
  AND c.level IN ('ND1', 'ND2');

-- ND I FIRST SEMESTER (from screenshot)
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM111', 'INTRODUCTION TO COMPUTING', 3, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM112', 'INTRODUCTION TO DIGITAL ELECTRONICS', 3, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM113', 'INTRODUCTION TO PROGRAMMING I', 4, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM114', 'STATISTICS FOR COMPUTING I', 2, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM115', 'COMPUTING APPLICATION PACKAGE I', 3, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS100', 'RELIGION AND MORALITY', 1, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS101', 'USE OF ENGLISH I', 2, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS127', 'CITIZENSHIP EDUCATION I', 4, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'MTH111', 'LOGIC AND LINEAR ALGEBRA', 2, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';

-- ND I SECOND SEMESTER (from screenshot)
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM121', 'PROGRAMMING USING C LANGUAGE', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM122', 'INTRODUCTION TO INTERNET', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM123', 'PROGRAMMING LANGUAGE USING JAVA I', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM124', 'DATA STRUCTURE AND ALGORITHMS', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM125', 'INTRODUCTION TO SYSTEMS ANALYSIS AND DESIGN', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM126', 'PC UPGRADE & MAINTENANCE', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'EED126', 'PRACTICE OF ENTREPRENEURSHIP', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS102', 'COMMUNICATION IN ENGLISH', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS128', 'CITIZENSHIP EDUCATION II', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS228', 'RESEARCH METHODS', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science';

-- ND II FIRST SEMESTER (from screenshot)
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM211', 'PROGRAMMING USING JAVA II', 4, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM212', 'INTRODUCTION TO SYSTEMS PROGRAMMING', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM213', 'UNIFIED MODELLING LANGUAGE [UML]', 3, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM214', 'COMPUTER SYSTEM TROUBLESHOOTING', 3, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM215', 'COMPUTER APPLICATION PACKAGES II', 3, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM216', 'STATISTICS FOR COMPUTING II', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM218', 'INTRODUCTION TO DATABASE DESIGN', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'EED216', 'PRACTICE OF ENTREPRENEURSHIP', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS201', 'USE OF ENGLISH II', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'SIW219', 'SIWES', 4, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'GNS101', 'USE OF ENGLISH I', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';

CREATE INDEX IF NOT EXISTS idx_notifications_student_read ON notifications (student_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_audience_read ON notifications (audience, is_read);
CREATE INDEX IF NOT EXISTS idx_results_student_session_semester ON results (student_id, session, semester);
CREATE INDEX IF NOT EXISTS idx_results_student_course ON results (student_id, course_id);