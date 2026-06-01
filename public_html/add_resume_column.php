<?php
require_once 'db_config.php';
$conn = getDBConnection();

// Add resume column to itcp table
$sql = "ALTER TABLE itcp ADD COLUMN resume VARCHAR(255) AFTER photo";

if ($conn->query($sql) === TRUE) {
    echo "Resume column added successfully";
} else {
    echo "Error adding resume column: " . $conn->error;
}

$conn->close();
?> 