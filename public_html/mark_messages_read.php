<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

// Check if coordinator is logged in
if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['mark_all']) && $data['mark_all'] === true) {
    // Mark all messages as read
    $sql = "UPDATE contactmessages SET is_read = 1";
    
    if ($conn->query($sql)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update messages']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?> 