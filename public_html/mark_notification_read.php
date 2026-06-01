<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;

if ($notification_id > 0) {
    // Mark the notification as read
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
    }
    
    $stmt->close();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
}

$conn->close();
?> 