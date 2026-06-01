<?php
require_once 'db_config.php';
$conn = getDBConnection();

$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$message = isset($input['message']) ? trim($input['message']) : '';

if ($user_id <= 0 || $message === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Insert a notification to the user
$title = 'More Information Required';
$type = 'verification_request';
$stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, ?, ?, ?, NOW())");
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to prepare statement']);
    exit;
}
$stmt->bind_param('isss', $user_id, $type, $title, $message);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'Failed to send request']);
    exit;
}

echo json_encode(['success' => true]);
?>


