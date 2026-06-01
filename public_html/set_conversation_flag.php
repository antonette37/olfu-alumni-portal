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
$conn->query("CREATE TABLE IF NOT EXISTS conversation_flags (
	user_id INT NOT NULL,
	other_user_id INT NOT NULL,
	archived_at TIMESTAMP NULL DEFAULT NULL,
	deleted_at TIMESTAMP NULL DEFAULT NULL,
	PRIMARY KEY (user_id, other_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$data = json_decode(file_get_contents('php://input'), true);
$user_id = (int)$_SESSION['user_id'];
$other_id = isset($data['other_id']) ? (int)$data['other_id'] : 0;
$action = isset($data['action']) ? trim((string)$data['action']) : '';

if ($other_id <= 0 || $action === '') {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid request']);
	exit();
}

// Only allow actions on existing users (optional safety)
$stmt = $conn->prepare('SELECT id FROM itcp WHERE id = ?');
if ($stmt) {
	$stmt->bind_param('i', $other_id);
	$stmt->execute();
	if ($stmt->get_result()->num_rows === 0) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'User not found']);
		exit();
	}
	$stmt->close();
}

// Upsert helper
function ensureRow($conn, $user_id, $other_id) {
	$stmt = $conn->prepare('INSERT INTO conversation_flags (user_id, other_user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id');
	$stmt->bind_param('ii', $user_id, $other_id);
	$stmt->execute();
	$stmt->close();
}

ensureRow($conn, $user_id, $other_id);

switch ($action) {
	case 'archive':
		$stmt = $conn->prepare('UPDATE conversation_flags SET archived_at = NOW() WHERE user_id = ? AND other_user_id = ?');
		break;
	case 'unarchive':
		$stmt = $conn->prepare('UPDATE conversation_flags SET archived_at = NULL WHERE user_id = ? AND other_user_id = ?');
		break;
	case 'delete_me':
		$stmt = $conn->prepare('UPDATE conversation_flags SET deleted_at = NOW() WHERE user_id = ? AND other_user_id = ?');
		break;
	default:
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'Unknown action']);
		exit();
}

$stmt->bind_param('ii', $user_id, $other_id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
	echo json_encode(['success' => true]);
} else {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed']);
}


