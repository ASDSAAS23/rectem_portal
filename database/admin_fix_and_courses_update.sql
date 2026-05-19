-- RUN THIS WHOLE FILE IN phpMyAdmin ON THE rectem_portal DATABASE

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

DELETE FROM admins;

INSERT INTO admins (staff_id, full_name, email, password, department_id, role)
SELECT 'CSC001', 'Computer Science Admin', 'cscadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'SLT001', 'SLT Admin', 'sltadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Science Laboratory Technology'
UNION ALL
SELECT 'EEE001', 'Electrical Admin', 'eeeadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Electrical/Electronics Engineering'
UNION ALL
SELECT 'BAM001', 'Business Admin', 'bamadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Business Administration'
UNION ALL
SELECT 'ACC001', 'Accountancy Admin', 'accadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Accountancy'
UNION ALL
SELECT 'CVE001', 'Civil Engineering Admin', 'cveadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Civil Engineering'
UNION ALL
SELECT 'ARC001', 'Architecture Admin', 'arcadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Architectural Technology'
UNION ALL
SELECT 'ESM001', 'Estate Management Admin', 'esmadmin@rectem.edu.ng', 'admin123', id, 'admin'
FROM departments WHERE department_name = 'Estate Management & Valuation';

INSERT IGNORE INTO student_records (matric_number, full_name, department_id, level)
SELECT 'R2024/620/014', 'ADEBOWALE ADEYINKA JOSIAH', id, 'ND1'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'R2024/610/001', 'Daniel SLT', id, 'ND1'
FROM departments WHERE department_name = 'Science Laboratory Technology'
UNION ALL
SELECT 'R2024/430/001', 'Musa Electrical', id, 'ND1'
FROM departments WHERE department_name = 'Electrical/Electronics Engineering'
UNION ALL
SELECT 'R2024/720/001', 'Esther Business', id, 'ND1'
FROM departments WHERE department_name = 'Business Administration'
UNION ALL
SELECT 'R2024/710/001', 'Grace Accountancy', id, 'ND1'
FROM departments WHERE department_name = 'Accountancy'
UNION ALL
SELECT 'R2024/410/001', 'Paul Civil', id, 'ND1'
FROM departments WHERE department_name = 'Civil Engineering'
UNION ALL
SELECT 'R2024/510/001', 'John Architecture', id, 'ND1'
FROM departments WHERE department_name = 'Architectural Technology'
UNION ALL
SELECT 'R2024/520/001', 'Tayo Estate', id, 'ND1'
FROM departments WHERE department_name = 'Estate Management & Valuation';

DELETE FROM courses WHERE department_id = (SELECT id FROM departments WHERE department_name='Computer Science');

INSERT INTO courses (course_code, course_title, unit, department_id, level, semester)
SELECT 'COM111', 'Introduction to Computing', 3, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM112', 'Introduction to Digital Electronics', 3, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM113', 'Introduction to Programming', 4, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM114', 'Statistics for Computing I', 2, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM115', 'Computing Application Package I', 3, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS100', 'Religion and Morality', 1, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS101', 'Use of English I', 2, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS127', 'Citizenship Education I', 4, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'MTH111', 'Logic and Linear Algebra', 2, id, 'ND1', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM121', 'Programming Using C Language', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM122', 'Introduction to Internet', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM123', 'Programming Language Using Java I', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM124', 'Data Structure and Algorithms', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM125', 'Introduction to Systems Analysis and Design', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM126', 'PC Upgrade & Maintenance', 3, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'EED126', 'Practice of Entrepreneurship', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS102', 'Communication in English', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS128', 'Citizenship Education II', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS228', 'Research Methods', 2, id, 'ND1', 'Second Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM211', 'Programming Language Using Java II', 4, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM212', 'Introduction to Systems Programming', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM213', 'Unified Modelling Language (UML)', 3, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM214', 'Computer System Troubleshooting', 3, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM215', 'Computer Application Packages II', 3, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM216', 'Statistics for Computing II', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'COM218', 'Introduction to Database Design', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'EED215', 'Practice of Entrepreneurship', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS201', 'Use of English II', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'SIW219', 'SIWES', 4, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science'
UNION ALL
SELECT 'GNS101', 'Use of English I', 2, id, 'ND2', 'First Semester'
FROM departments WHERE department_name = 'Computer Science';
