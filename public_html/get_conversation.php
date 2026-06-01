<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
	header('Content-Type: application/json');
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit();
}

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$other_id = isset($_GET['other_id']) ? (int)$_GET['other_id'] : 0;

if ($current_user_id <= 0 || $other_id <= 0) {
	header('Content-Type: application/json');
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid participants']);
	exit();
}

$stmt = $conn->prepare(
    "SELECT m.*, 
            s.firstname AS sender_firstname, s.lastname AS sender_lastname, s.photo AS sender_photo,
            r.firstname AS receiver_firstname, r.lastname AS receiver_lastname, r.photo AS receiver_photo
     FROM messages m
     JOIN itcp s ON m.sender_id = s.id
     JOIN itcp r ON m.receiver_id = r.id
     WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
     ORDER BY m.created_at ASC"
);
if (!$stmt) {
	header('Content-Type: application/json');
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Prepare failed']);
	exit();
}
$stmt->bind_param('iiii', $current_user_id, $other_id, $other_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch attachments for returned messages
$ids = array_column($messages, 'id');
$attachmentsByMessage = [];
if (!empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $sql = "SELECT id, message_id, original_name, stored_name, mime_type, file_size FROM messages_attachments WHERE message_id IN ($in) ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $mid = (int)$row['message_id'];
            if (!isset($attachmentsByMessage[$mid])) $attachmentsByMessage[$mid] = [];
            $attachmentsByMessage[$mid][] = $row;
        }
        $stmt->close();
    }
}

foreach ($messages as &$m) {
    $mid = (int)$m['id'];
    $m['attachments'] = $attachmentsByMessage[$mid] ?? [];
    // Mask content if deleted for everyone
    if (!empty($m['deleted_for_everyone_at'])) {
        $m['message'] = '[message deleted]';
        $m['subject'] = $m['subject'] ? '[deleted] ' . $m['subject'] : '[deleted]';
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'messages' => $messages]);


