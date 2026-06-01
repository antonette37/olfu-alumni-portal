<?php
session_start();

if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
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

// Update the record status
$query = "UPDATE itcp SET status = 'verified', verified_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Log the verification
    $log_query = "INSERT INTO system_logs (action, table_name, record_id, user_id, details) 
                  VALUES ('verify', 'itcp', ?, ?, 'Alumni record verified')";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("ii", $id, $_SESSION['coordinator_id']);
    $log_stmt->execute();
    $log_stmt->close();

    // Send notification to alumni
    $notification_query = "INSERT INTO notifications (user_id, type, message, created_at) 
                          SELECT id, 'verification', 'Your alumni record has been verified', NOW() 
                          FROM itcp WHERE id = ?";
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param("i", $id);
    $notification_stmt->execute();
    $notification_stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Record verified successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error verifying record']);
}

$stmt->close();
$conn->close();
?> 