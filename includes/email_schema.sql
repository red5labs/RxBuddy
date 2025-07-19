-- Email Reminder Database Schema Updates

-- Add email reminder preferences to users table
ALTER TABLE users ADD COLUMN email_reminders BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(255);
ALTER TABLE users ADD COLUMN email_verification_expires DATETIME;

-- Add reminder settings to medications table
ALTER TABLE medications ADD COLUMN reminder_enabled BOOLEAN DEFAULT TRUE;
ALTER TABLE medications ADD COLUMN reminder_offset_minutes INT DEFAULT 0; -- Remind X minutes before

-- Create reminders table for tracking sent reminders
CREATE TABLE reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medication_id INT NOT NULL,
    user_id INT NOT NULL,
    scheduled_time DATETIME NOT NULL,
    reminder_type ENUM('email', 'sms') DEFAULT 'email',
    sent_at DATETIME,
    status ENUM('pending', 'sent', 'cancelled', 'failed') DEFAULT 'pending',
    error_message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medication_id) REFERENCES medications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_scheduled_time (scheduled_time),
    INDEX idx_status (status),
    INDEX idx_user_medication (user_id, medication_id)
);

-- Create email_logs table for tracking email delivery
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed') NOT NULL,
    error_message TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
); 