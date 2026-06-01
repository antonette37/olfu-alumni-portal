<?php
session_start();

// Check if coordinator is logged in
if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

require_once 'db_config.php';
$conn = getDBConnection();

// Mark all notifications as read
$sql = "UPDATE notifications SET is_read = 1 WHERE type = 'new_registration' AND is_read = 0";
$stmt = $conn->prepare($sql);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
}

$stmt->close();
$conn->close();
?> 