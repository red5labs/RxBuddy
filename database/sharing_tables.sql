-- Profile Sharing Tables
-- This adds functionality for users to share their medication information with caregivers, family, or healthcare providers

-- Table for shared profiles
CREATE TABLE IF NOT EXISTS shared_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    share_name VARCHAR(255) NOT NULL,
    share_email VARCHAR(255) NOT NULL,
    share_type ENUM('caregiver', 'family', 'healthcare_provider', 'other') NOT NULL,
    permissions JSON NOT NULL, -- Store permissions as JSON: {"view_medications": true, "view_logs": true, "view_calendar": true, "receive_alerts": false}
    share_token VARCHAR(64) UNIQUE NOT NULL, -- Unique token for sharing link
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME NULL, -- Optional expiration date
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_share_token (share_token),
    INDEX idx_share_email (share_email)
);

-- Table for tracking shared profile access
CREATE TABLE IF NOT EXISTS shared_profile_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shared_profile_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shared_profile_id) REFERENCES shared_profiles(id) ON DELETE CASCADE,
    INDEX idx_shared_profile_id (shared_profile_id),
    INDEX idx_accessed_at (accessed_at)
);

-- Table for emergency contacts (optional feature)
CREATE TABLE IF NOT EXISTS emergency_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    relationship VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Add sharing preferences to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS sharing_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS emergency_contacts_enabled BOOLEAN DEFAULT FALSE;

-- Insert sample data for testing (optional)
-- INSERT INTO shared_profiles (user_id, share_name, share_email, share_type, permissions, share_token) 
-- VALUES (1, 'Dr. Smith', 'dr.smith@example.com', 'healthcare_provider', '{"view_medications": true, "view_logs": true, "view_calendar": true, "receive_alerts": false}', 'sample_token_123'); 