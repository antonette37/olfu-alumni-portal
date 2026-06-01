<?php
require_once 'db_config.php';
$conn = getDBConnection();

$host = 'localhost';
$user = 'root';
$pass = '';  // Default XAMPP MySQL root password is empty
$db = 'itcp_db';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// First, check if the table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'user_logs'");
if ($tableExists->num_rows > 0) {
    echo "Table user_logs already exists.<br>";
} else {
    // SQL to create the user_logs table
    $sql = "CREATE TABLE `user_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `activity_type` varchar(50) NOT NULL,
        `activity_description` text NOT NULL,
        `ip_address` varchar(45) NOT NULL,
        `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if ($conn->query($sql) === TRUE) {
        echo "Table user_logs created successfully.<br>";
        
        // Now add the foreign key constraint
        $fk_sql = "ALTER TABLE `user_logs` 
                   ADD CONSTRAINT `user_logs_ibfk_1` 
                   FOREIGN KEY (`user_id`) 
                   REFERENCES `itcp` (`id`) 
                   ON DELETE CASCADE";
        
        if ($conn->query($fk_sql) === TRUE) {
            echo "Foreign key constraint added successfully.<br>";
        } else {
            echo "Error adding foreign key constraint: " . $conn->error . "<br>";
        }
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

// Verify the table structure
$result = $conn->query("DESCRIBE user_logs");
if ($result) {
    echo "<br>Table structure:<br>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "<br>";
    }
} else {
    echo "Error checking table structure: " . $conn->error . "<br>";
}

$conn->close();
?> 