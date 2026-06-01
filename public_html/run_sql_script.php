<?php
require_once 'db_config.php';
$conn = getDBConnection();

// Add error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sql = file_get_contents('alter_itcp_resume_fields.sql');

if ($conn->multi_query($sql)) {
    echo "SQL executed successfully.";
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error executing SQL script: " . $conn->error;
}

$conn->close();
?> 