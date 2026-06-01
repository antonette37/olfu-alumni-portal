<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once '../../db_config.php';
require_once __DIR__ . '/includes/mobile_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$sender_id = mobile_auth_user_id();
if (!$sender_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$receiver_id = (int)($input['receiver_id'] ?? 0);
$message = trim($input['message'] ?? '');
$attachment = $input['attachment'] ?? null;

if (!$receiver_id || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Receiver ID and message are required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Find or create conversation
    $conv_stmt = $conn->prepare('SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)');
    $conv_stmt->bind_param('iiii', $sender_id, $receiver_id, $receiver_id, $sender_id);
    $conv_stmt->execute();
    $conv_result = $conv_stmt->get_result();
    $conversation = $conv_result->fetch_assoc();
    $conv_stmt->close();
    
    $conversation_id = null;
    if ($conversation) {
        $conversation_id = $conversation['id'];
    } else {
        // Create new conversation
        $create_conv_stmt = $conn->prepare('INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)');
        $create_conv_stmt->bind_param('ii', $sender_id, $receiver_id);
        $create_conv_stmt->execute();
        $conversation_id = $conn->insert_id;
        $create_conv_stmt->close();
    }
    
    // Insert message
    $msg_stmt = $conn->prepare('INSERT INTO messages (conversation_id, sender_id, receiver_id, message, attachment) VALUES (?, ?, ?, ?, ?)');
    $msg_stmt->bind_param('iiiss', $conversation_id, $sender_id, $receiver_id, $message, $attachment);
    
    if ($msg_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
    
    $msg_stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

