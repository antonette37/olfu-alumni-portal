<?php
session_start();

// Check if user is logged in as coordinator
if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "itcp_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Delete the record
$id = $conn->real_escape_string($data['id']);
$sql = "DELETE FROM itcp WHERE id = '$id'";

if ($conn->query($sql) === TRUE) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting record: ' . $conn->error]);
}

$conn->close();
?> 