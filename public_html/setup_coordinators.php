<?php
require_once 'db_config.php';
$conn = getDBConnection();

// SQL to create coordinators table
$sql = "CREATE TABLE IF NOT EXISTS coordinators (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Execute table creation
if ($conn->query($sql) === TRUE) {
    echo "Coordinators table created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Check if default coordinator exists
$check_sql = "SELECT id FROM coordinators WHERE username = 'coordinator'";
$result = $conn->query($check_sql);

if ($result->num_rows == 0) {
    // Insert default coordinator with hashed password
    $username = 'coordinator';
    $plain = 'password1';
    $hashed = password_hash($plain, PASSWORD_DEFAULT);
    $firstname = 'Default';
    $lastname = 'Coordinator';
    $email = 'coordinator@olfu.edu.ph';

    $insert_stmt = $conn->prepare("INSERT INTO coordinators (username, password, firstname, lastname, email) VALUES (?, ?, ?, ?, ?)");
    if ($insert_stmt) {
        $insert_stmt->bind_param('sssss', $username, $hashed, $firstname, $lastname, $email);
        $ok = $insert_stmt->execute();
        $insert_stmt->close();
    } else {
        $ok = false;
        echo "Error preparing insert: " . $conn->error . "<br>";
    }

    if ($ok === TRUE) {
        echo "Default coordinator account created successfully<br>";
    } else {
        echo "Error creating default coordinator.<br>";
    }
} else {
    echo "Default coordinator account already exists<br>";
}

$conn->close();
?> 