<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once '../../db_config.php';
require_once __DIR__ . '/includes/resolve_profile_image.php';
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

try {
    $conn = getDBConnection();
    $conversations = [];

    $tableCheck = @$conn->query("SHOW TABLES LIKE 'conversations'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $stmt = @$conn->prepare('
        SELECT c.id, c.user1_id, c.user2_id, 
               (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
               (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND receiver_id = ? AND is_read = 0) as unread_count
        FROM conversations c
        WHERE (c.user1_id = ? OR c.user2_id = ?)
        ORDER BY last_message_time DESC
    ');
    if (!$stmt) {
        echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $stmt->bind_param('iii', $user_id, $user_id, $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    while ($row = $result->fetch_assoc()) {
        // Get other user info
        $other_user_id = ($row['user1_id'] == $user_id) ? $row['user2_id'] : $row['user1_id'];
        // Use correct column name: photo instead of profile_image
        $user_stmt = $conn->prepare('SELECT id, email, firstname, lastname, photo FROM itcp WHERE id = ?');
        $user_stmt->bind_param('i', $other_user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $other_user = $user_result->fetch_assoc();
        $user_stmt->close();
        
        // Map photo to profile_image for mobile app
        if ($other_user) {
            $ouPhoto = $other_user['photo'] ?? null;
            $other_user['profile_image'] = mobile_resolve_profile_image_url(
                $ouPhoto,
                'https://ccsolfualumni.sbs',
                (int) $other_user_id
            );
            $other_user['profile_image_data'] = mobile_resolve_profile_image_data($ouPhoto, (int) $other_user_id);
            unset($other_user['photo']);
        }
        
        $conversations[] = [
            'id' => $row['id'],
            'user1_id' => $row['user1_id'],
            'user2_id' => $row['user2_id'],
            'last_message' => $row['last_message'],
            'last_message_time' => $row['last_message_time'],
            'unread_count' => (int)$row['unread_count'],
            'other_user' => $other_user
        ];
    }
    $stmt->close();
    
    echo json_encode($conversations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

