<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "itcp_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Step 1: Add the column if it doesn't exist
$add_column_sql = "ALTER TABLE events ADD COLUMN created_by INT NULL";
if ($conn->query($add_column_sql) === TRUE) {
    echo "Column 'created_by' added successfully.<br>";
} else {
    if (strpos($conn->error, 'Duplicate column name') !== false) {
        echo "Column 'created_by' already exists.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
}

// Step 2: Try to add the foreign key (optional, may fail if data doesn't match)
$add_fk_sql = "ALTER TABLE events ADD CONSTRAINT fk_created_by FOREIGN KEY (created_by) REFERENCES coordinators(id)";
if ($conn->query($add_fk_sql) === TRUE) {
    echo "Foreign key constraint added successfully.<br>";
} else {
    if (strpos($conn->error, 'Duplicate') !== false || strpos($conn->error, 'already exists') !== false) {
        echo "Foreign key constraint already exists or duplicate.<br>";
    } else {
        echo "Error adding foreign key: " . $conn->error . "<br>";
    }
}

$conn->close();
?> 