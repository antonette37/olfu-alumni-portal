-- Create career_milestones table
CREATE TABLE IF NOT EXISTS career_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    milestone_type ENUM('Education', 'Employment', 'Certification', 'Award', 'Project', 'Other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    date_achieved DATE NOT NULL,
    visibility ENUM('Private', 'Admin Only', 'Public') DEFAULT 'Private',
    linkedin_sync BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Add career_visibility_settings table
CREATE TABLE IF NOT EXISTS career_visibility_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    visibility ENUM('Private', 'Admin Only', 'Public') DEFAULT 'Private',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE,
    UNIQUE KEY unique_section (alumni_id, section_name)
);

-- Add linkedin_integration table
CREATE TABLE IF NOT EXISTS linkedin_integration (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    linkedin_access_token VARCHAR(255),
    linkedin_refresh_token VARCHAR(255),
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
SELECT id, 'career_timeline', 'Public' FROM itcp;

INSERT INTO career_visibility_settings (alumni_id, section_name, visibility)
SELECT id, 'skills', 'Public' FROM itcp;

INSERT INTO career_visibility_settings (alumni_id, section_name, visibility)
SELECT id, 'achievements', 'Public' FROM itcp;

INSERT INTO career_visibility_settings (alumni_id, section_name, visibility)
SELECT id, 'certifications', 'Public' FROM itcp; 