USE itcp_db;

-- Create events table
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    type ENUM('workshop', 'seminar', 'conference', 'networking', 'other') NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    venue VARCHAR(255) NOT NULL,
    audience VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('draft', 'published', 'cancelled') DEFAULT 'draft',
    created_by INT,
    max_participants INT,
    registration_deadline DATETIME,
    is_online BOOLEAN DEFAULT FALSE,
    meeting_link VARCHAR(255),
    banner_image VARCHAR(255)
);

-- Create registrations table
CREATE TABLE IF NOT EXISTS registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    alumni_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled', 'no_show') DEFAULT 'registered',
    attendance_date DATETIME,
    feedback TEXT,
    rating INT,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Create event_attachments table for additional event files
CREATE TABLE IF NOT EXISTS event_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Create event_comments table for event feedback
CREATE TABLE IF NOT EXISTS event_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    alumni_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
); 