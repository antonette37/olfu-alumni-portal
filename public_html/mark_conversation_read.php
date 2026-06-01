<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$viewer_id = (int)($_SESSION['user_id'] ?? 0);
$other_id = isset($data['sender_id']) ? (int)$data['sender_id'] : 0;

if ($viewer_id <= 0 || $other_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid participants']);
	exit();
}

// Mark all messages sent by other party to the viewer as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Prepare failed']);
	exit();
}
$stmt->bind_param('ii', $other_id, $viewer_id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
	echo json_encode(['success' => true]);
} else {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Update failed']);
}


