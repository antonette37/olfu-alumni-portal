-- Drop existing tables if they exist (for clean migration)
DROP TABLE IF EXISTS gallery_images;
DROP TABLE IF EXISTS gallery_albums;

-- Create albums table
CREATE TABLE IF NOT EXISTS gallery_albums (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    subtitle TEXT,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'archived') DEFAULT 'active',
    created_by INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create images table
CREATE TABLE IF NOT EXISTS gallery_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    album_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    description TEXT,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create indexes for better performance
CREATE INDEX idx_album_id ON gallery_images(album_id);
CREATE INDEX idx_status ON gallery_albums(status);
CREATE INDEX idx_display_order_album ON gallery_albums(display_order);
CREATE INDEX idx_display_order_image ON gallery_images(display_order);
