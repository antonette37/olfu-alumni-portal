<?php
session_start();

// Check if user is logged in as coordinator
if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['id'])) {
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

// Sanitize input data
$id = $conn->real_escape_string($_POST['id']);
$firstname = $conn->real_escape_string($_POST['firstname']);
$lastname = $conn->real_escape_string($_POST['lastname']);
$email = $conn->real_escape_string($_POST['email']);
$program = $conn->real_escape_string($_POST['program']);
$year_graduated = $conn->real_escape_string($_POST['year_graduated']);
$status = $conn->real_escape_string($_POST['status']);

// Update the record
$sql = "UPDATE itcp SET 
        firstname = '$firstname',
        lastname = '$lastname',
        email = '$email',
        program = '$program',
        year_graduated = '$year_graduated',
        status = '$status'
        WHERE id = '$id'";

if ($conn->query($sql) === TRUE) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating record: ' . $conn->error]);
}

$conn->close();
?> 