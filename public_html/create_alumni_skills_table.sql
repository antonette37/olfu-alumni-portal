-- Create alumni_skills table
CREATE TABLE IF NOT EXISTS alumni_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    skill_level ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') NOT NULL,
    years_experience INT DEFAULT 0,
    certification VARCHAR(255),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Insert sample skills for testing
INSERT INTO alumni_skills (alumni_id, skill_name, skill_level, years_experience, certification) VALUES
(11, 'PHP', 'Advanced', 3, 'PHP Developer Certification'),
(11, 'MySQL', 'Intermediate', 2, NULL),
(11, 'JavaScript', 'Expert', 5, 'JavaScript Advanced Concepts'),
(11, 'HTML/CSS', 'Advanced', 4, 'Web Development Professional'),
(11, 'Laravel', 'Intermediate', 2, 'Laravel Framework Certification'),
(11, 'Git', 'Advanced', 3, 'Git Professional'),
(11, 'React', 'Intermediate', 2, 'React Developer Certification'),
(11, 'Node.js', 'Beginner', 1, NULL),
(11, 'Python', 'Intermediate', 2, 'Python Programming'),
(11, 'AWS', 'Beginner', 1, 'AWS Cloud Practitioner');

-- Add employment_status column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS employment_status ENUM('Employed', 'Unemployed', 'Self-employed', 'Further Studies') DEFAULT NULL;

-- Add industry column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS industry VARCHAR(100) DEFAULT NULL;

-- Add job_title column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS job_title VARCHAR(100) DEFAULT NULL;

-- Add company column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS company VARCHAR(100) DEFAULT NULL;

-- Add years_experience column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS years_experience INT DEFAULT NULL;

-- Add salary_range column to itcp table if it doesn't exist (with privacy settings)
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS salary_range VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS salary_visibility ENUM('Private', 'Admin Only', 'Public') DEFAULT 'Private';

-- Add linkedin_profile column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS linkedin_profile VARCHAR(255) DEFAULT NULL;

-- Add github_profile column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS github_profile VARCHAR(255) DEFAULT NULL;

-- Add portfolio_url column to itcp table if it doesn't exist
ALTER TABLE itcp
ADD COLUMN IF NOT EXISTS portfolio_url VARCHAR(255) DEFAULT NULL; 