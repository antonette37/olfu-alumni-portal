<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['message_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing message ID']);
    exit();
}

$message_id = $data['message_id'];
$user_id = $_SESSION['user_id'];

// Update message read status
$sql = "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $message_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark message as read']);
}

$stmt->close();
$conn->close(); 