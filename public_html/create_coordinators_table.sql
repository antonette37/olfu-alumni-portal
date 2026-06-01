USE itcp_db;

CREATE TABLE IF NOT EXISTS coordinators (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert a default coordinator account
INSERT INTO coordinators (username, password, firstname, lastname, email) 
VALUES ('coordinator', 'password1', 'Default', 'Coordinator', 'coordinator@olfu.edu.ph'); 