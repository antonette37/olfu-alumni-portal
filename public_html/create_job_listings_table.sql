-- Drop existing table if it exists
DROP TABLE IF EXISTS job_listings_new;

-- Create new job_listings table
CREATE TABLE job_listings_new (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    company VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    job_type ENUM('Full-time', 'Part-time', 'Contract', 'Internship') NOT NULL,
    industry VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT,
    salary_range VARCHAR(100),
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deadline_date DATE,
    status ENUM('active', 'closed', 'draft') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES itcp(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample job listings
INSERT INTO job_listings_new (title, company, location, job_type, industry, description, requirements, salary_range, deadline_date, status, created_by) VALUES
('Software Developer', 'Tech Solutions Inc.', 'Manila', 'Full-time', 'Information Technology', 'We are looking for a skilled software developer to join our team...', 'Bachelor''s degree in Computer Science or related field\n3+ years of experience\nStrong knowledge of PHP and MySQL', '₱40,000 - ₱60,000', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'active', 1),
('Marketing Specialist', 'Global Marketing Co.', 'Quezon City', 'Full-time', 'Marketing', 'Join our dynamic marketing team...', 'Bachelor''s degree in Marketing or related field\n2+ years of experience\nExcellent communication skills', '₱35,000 - ₱45,000', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'active', 1),
('Nurse', 'Metro Hospital', 'Makati', 'Full-time', 'Healthcare', 'Looking for registered nurses to join our healthcare team...', 'BSN degree\nValid PRC license\n2+ years of hospital experience', '₱30,000 - ₱40,000', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'active', 1),
('Business Analyst', 'Finance Corp', 'Taguig', 'Full-time', 'Finance', 'Seeking a business analyst to help drive our financial operations...', 'Bachelor''s degree in Business or Finance\n3+ years of experience\nStrong analytical skills', '₱45,000 - ₱65,000', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'active', 1),
('Graphic Designer', 'Creative Studio', 'Pasig', 'Part-time', 'Creative', 'Join our creative team as a graphic designer...', 'Bachelor''s degree in Design or related field\nPortfolio required\n2+ years of experience', '₱25,000 - ₱35,000', DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'active', 1);

-- Rename tables
RENAME TABLE job_listings TO job_listings_old, job_listings_new TO job_listings;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS job_listings;
SET FOREIGN_KEY_CHECKS=1; 