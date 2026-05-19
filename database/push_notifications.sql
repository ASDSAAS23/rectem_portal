-- RUN THIS FILE IN phpMyAdmin ON THE rectem_portal DATABASE
-- Adds push notification support tables and columns

-- Table to store browser push subscriptions
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(500) NOT NULL,
    auth_key VARCHAR(500) NOT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    CONSTRAINT fk_push_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Store VAPID keys in portal_settings
INSERT INTO portal_settings (setting_key, setting_value) VALUES
('vapid_public_key', ''),
('vapid_private_key', ''),
('vapid_subject', 'mailto:admin@rectem.edu.ng')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- Add a column to track the last notification id each student has seen (for polling)
ALTER TABLE students ADD COLUMN IF NOT EXISTS last_seen_notification_id INT DEFAULT 0;
