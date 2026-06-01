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

require_once 'db_config.php';
$conn = getDBConnection();

// Update the record status
$query = "UPDATE itcp SET status = 'rejected', rejected_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Log the rejection
    $log_query = "INSERT INTO system_logs (action, table_name, record_id, user_id, details) 
                  VALUES ('reject', 'itcp', ?, ?, 'Alumni record rejected')";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("ii", $id, $_SESSION['coordinator_id']);
    $log_stmt->execute();
    $log_stmt->close();

    // Send notification to alumni
    $notification_query = "INSERT INTO notifications (user_id, type, message, created_at) 
                          SELECT id, 'rejection', 'Your alumni record has been rejected. Please contact the coordinator for more information.', NOW() 
                          FROM itcp WHERE id = ?";
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param("i", $id);
    $notification_stmt->execute();
    $notification_stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Record rejected successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error rejecting record']);
}

$stmt->close();
$conn->close();
?> 