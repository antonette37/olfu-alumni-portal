-- Create companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    website VARCHAR(255),
    description TEXT,
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create job_listings table
CREATE TABLE IF NOT EXISTS job_listings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    employment_type ENUM('Full-time', 'Part-time', 'Contract', 'Internship') NOT NULL,
    location VARCHAR(255) NOT NULL,
    salary_range_min DECIMAL(10,2),
    salary_range_max DECIMAL(10,2),
    status ENUM('active', 'closed', 'draft') DEFAULT 'active',
    posted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

-- Create job_skills table
CREATE TABLE IF NOT EXISTS job_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_listings(id) ON DELETE CASCADE
);

-- Create job_applications table
CREATE TABLE IF NOT EXISTS job_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    alumni_id INT NOT NULL,
    status ENUM('pending', 'reviewed', 'shortlisted', 'rejected', 'hired') DEFAULT 'pending',
    cover_letter TEXT,
    resume_url VARCHAR(255),
    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_listings(id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Insert sample companies
INSERT INTO companies (company_name, industry, location, website, description) VALUES
('Tech Solutions Inc.', 'Information Technology', 'Manila, Philippines', 'https://techsolutions.com', 'Leading IT solutions provider in the Philippines'),
('Digital Innovations', 'Software Development', 'Quezon City, Philippines', 'https://digitalinnovations.com', 'Innovative software development company'),
('Data Systems Corp', 'Data Analytics', 'Makati, Philippines', 'https://datasystems.com', 'Data analytics and business intelligence solutions'),
('Web Creations', 'Web Development', 'Pasig, Philippines', 'https://webcreations.com', 'Creative web development agency'),
('Cloud Services PH', 'Cloud Computing', 'Taguig, Philippines', 'https://cloudservices.ph', 'Cloud infrastructure and services provider');

-- Insert sample job listings
INSERT INTO job_listings (company_id, title, description, employment_type, location, salary_range_min, salary_range_max, expiry_date) VALUES
(1, 'Senior Software Developer', 'Looking for an experienced software developer to join our team...', 'Full-time', 'Manila, Philippines', 50000, 80000, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)),
(2, 'UI/UX Designer', 'Creative UI/UX designer needed for our growing team...', 'Full-time', 'Quezon City, Philippines', 40000, 60000, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)),
(3, 'Data Analyst', 'Join our data analytics team to help drive business decisions...', 'Full-time', 'Makati, Philippines', 45000, 70000, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)),
(4, 'Frontend Developer', 'Experienced frontend developer needed for web projects...', 'Contract', 'Pasig, Philippines', 35000, 55000, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)),
(5, 'Cloud Engineer', 'Cloud infrastructure specialist needed for our growing team...', 'Full-time', 'Taguig, Philippines', 60000, 90000, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY));

-- Insert sample job skills
INSERT INTO job_skills (job_id, skill_name) VALUES
(1, 'PHP'), (1, 'MySQL'), (1, 'JavaScript'), (1, 'Laravel'),
(2, 'UI Design'), (2, 'UX Design'), (2, 'Figma'), (2, 'Adobe XD'),
(3, 'Python'), (3, 'SQL'), (3, 'Data Analysis'), (3, 'Tableau'),
(4, 'HTML'), (4, 'CSS'), (4, 'JavaScript'), (4, 'React'),
(5, 'AWS'), (5, 'Azure'), (5, 'Docker'), (5, 'Kubernetes'); 