<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once '../../db_config.php';
require_once __DIR__ . '/includes/mobile_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = mobile_auth_user_id();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conversation_id = $_GET['conversation_id'] ?? 0;

if (!$conversation_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Conversation ID is required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare('SELECT id, conversation_id, sender_id, receiver_id, message, attachment, created_at, is_read FROM messages WHERE conversation_id = ? ORDER BY created_at ASC');
    $stmt->bind_param('i', $conversation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    
    // Mark messages as read
    $update_stmt = $conn->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0');
    $update_stmt->bind_param('ii', $conversation_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    echo json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

