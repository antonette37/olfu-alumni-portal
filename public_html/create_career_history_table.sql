-- Create career_history table
CREATE TABLE IF NOT EXISTS career_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alumni_id INT NOT NULL,
    job_title VARCHAR(100) NOT NULL,
    company VARCHAR(100) NOT NULL,
    industry VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE,
    description TEXT,
    skills_used TEXT,
    salary DECIMAL(10,2),
    year INT GENERATED ALWAYS AS (YEAR(start_date)) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
); 