<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$message_id = isset($data['message_id']) ? (int)$data['message_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if ($message_id <= 0) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid message id']);
	exit();
}

// Ensure schema has deleted_for_everyone_at; try to add if missing (idempotent)
@$conn->query("ALTER TABLE messages ADD COLUMN deleted_for_everyone_at TIMESTAMP NULL DEFAULT NULL");

// Fetch message
$stmt = $conn->prepare('SELECT id, sender_id, created_at, deleted_for_everyone_at FROM messages WHERE id = ?');
if (!$stmt) { http_response_code(500); echo json_encode(['success' => false]); exit(); }
$stmt->bind_param('i', $message_id);
$stmt->execute();
$res = $stmt->get_result();
$msg = $res->fetch_assoc();
$stmt->close();

if (!$msg) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Not found']); exit(); }
if ((int)$msg['sender_id'] !== $user_id) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Forbidden']); exit(); }
if (!empty($msg['deleted_for_everyone_at'])) { echo json_encode(['success' => true]); exit(); }

// 10-minute window
$created = strtotime($msg['created_at']);
if (time() - $created > 10 * 60) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Delete window expired']);
	exit();
}

// Mark as deleted for everyone
$stmt = $conn->prepare('UPDATE messages SET deleted_for_everyone_at = NOW() WHERE id = ?');
$stmt->bind_param('i', $message_id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => (bool)$ok]);


