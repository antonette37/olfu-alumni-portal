<?php
session_start();
require_once 'config.php';

// Require login
if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	exit('Unauthorized');
}

$attachment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($attachment_id <= 0) {
	http_response_code(400);
	exit('Invalid request');
}

// Join to messages to ensure requester is participant (privacy)
$stmt = $conn->prepare(
	"SELECT a.id, a.original_name, a.stored_name, a.mime_type, a.file_size, m.sender_id, m.receiver_id
	 FROM messages_attachments a
	 JOIN messages m ON m.id = a.message_id
	 WHERE a.id = ?"
);
if (!$stmt) { http_response_code(500); exit('Server error'); }
$stmt->bind_param('i', $attachment_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) { http_response_code(404); exit('Not found'); }

$user_id = (int)$_SESSION['user_id'];
if ($row['sender_id'] != $user_id && $row['receiver_id'] != $user_id) {
	http_response_code(403);
	exit('Forbidden');
}

$path = __DIR__ . '/' . $row['stored_name'];
if (!is_file($path)) { http_response_code(404); exit('File missing'); }

$mime = $row['mime_type'] ?: 'application/octet-stream';
$name = $row['original_name'] ?: 'attachment';
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($name) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;


