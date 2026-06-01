<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Ensure alumni user is logged in
if (empty($_SESSION['user_id'])) {
	echo json_encode(['success' => false, 'message' => 'Not authorized']);
	exit();
}

$userId = (int)$_SESSION['user_id'];

// Mark all notifications for this user as read (including NULL values)
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)");
if (!$stmt) {
	echo json_encode(['success' => false, 'message' => 'Prepare failed']);
	exit();
}
$stmt->bind_param('i', $userId);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($ok) {
	echo json_encode(['success' => true, 'updated' => $affected]);
} else {
	echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>
