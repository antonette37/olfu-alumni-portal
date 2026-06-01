-- Create suggested_milestones table
CREATE TABLE IF NOT EXISTS suggested_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    milestone_type ENUM('Education', 'Employment', 'Certification', 'Award', 'Project', 'Other') NOT NULL,
    date_achieved DATE,
    source VARCHAR(50) NOT NULL DEFAULT 'LinkedIn',
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Create career_history table for version tracking
CREATE TABLE IF NOT EXISTS career_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    milestone_id INT NOT NULL,
    alumni_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    milestone_type ENUM('Education', 'Employment', 'Certification', 'Award', 'Project', 'Other') NOT NULL,
    date_achieved DATE,
    visibility ENUM('Private', 'Admin Only', 'Public') NOT NULL DEFAULT 'Private',
    changes TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (milestone_id) REFERENCES career_milestones(id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Create career_visibility_settings table if not exists
CREATE TABLE IF NOT EXISTS career_visibility_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    visibility ENUM('Private', 'Admin Only', 'Public') NOT NULL DEFAULT 'Private',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_section (alumni_id, section_name),
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Create linkedin_integration table if not exists
CREATE TABLE IF NOT EXISTS linkedin_integration (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    access_token TEXT,
    token_expiry TIMESTAMP NULL,
    last_sync TIMESTAMP NULL,
    sync_career BOOLEAN DEFAULT FALSE,
    sync_skills BOOLEAN DEFAULT FALSE,
    sync_certifications BOOLEAN DEFAULT FALSE,
    auto_sync BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Insert default visibility settings for existing alumni
INSERT IGNORE INTO career_visibility_settings (alumni_id, section_name, visibility)
SELECT id, 'career_timeline', 'Private' FROM itcp;

INSERT IGNORE INTO career_visibility_settings (alumni_id, section_name, visibility)
SELECT id, 'skills', 'Private' FROM itcp;

INSERT IGNORE INTO career_visibility_settings (alumni_id, section_name, visibility)
SELECT id, 'achievements', 'Private' FROM itcp;

-- Add career-related columns to itcp table if they don't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS current_position VARCHAR(255) NULL AFTER position,
ADD COLUMN IF NOT EXISTS skills TEXT NULL AFTER current_position,
ADD COLUMN IF NOT EXISTS education TEXT NULL AFTER skills; 