USE itcp_db;

CREATE TABLE IF NOT EXISTS alumni_success_stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    author_program VARCHAR(255) NOT NULL,
    author_year INT NOT NULL,
    author_photo VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    featured BOOLEAN DEFAULT FALSE,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    FOREIGN KEY (author_id) REFERENCES itcp(id) ON DELETE CASCADE
);

-- Add featured column to itcp table if it doesn't exist
ALTER TABLE itcp ADD COLUMN IF NOT EXISTS featured BOOLEAN DEFAULT FALSE;
ALTER TABLE itcp ADD COLUMN IF NOT EXISTS bio TEXT;
ALTER TABLE itcp ADD COLUMN IF NOT EXISTS linkedin VARCHAR(255);
ALTER TABLE itcp ADD COLUMN IF NOT EXISTS twitter VARCHAR(255); 