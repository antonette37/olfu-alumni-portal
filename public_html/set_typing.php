<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit();
}

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS typing_status (
	user_id INT NOT NULL,
	other_user_id INT NOT NULL,
	is_typing TINYINT(1) NOT NULL DEFAULT 0,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, other_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)$_SESSION['user_id'];
$other_id = isset($data['other_id']) ? (int)$data['other_id'] : 0;
$is_typing = isset($data['is_typing']) ? (int)(!!$data['is_typing']) : 0;

if ($other_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid request']);
	exit();
}

$stmt = $conn->prepare('INSERT INTO typing_status (user_id, other_user_id, is_typing) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), updated_at = CURRENT_TIMESTAMP');
if (!$stmt) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Server error']);
	exit();
}
$stmt->bind_param('iii', $user_id, $other_id, $is_typing);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => (bool)$ok]);


