<?php
// Enable error logging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('Fatal error in send_message.php: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Server error occurred. Please check logs.']);
        }
    }
});

session_start();

try {
    require_once 'config.php';
} catch (Exception $e) {
    error_log('Failed to load config.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Configuration error']);
    exit();
}

header('Content-Type: application/json');

// Check database connection
if (!isset($conn) || !$conn) {
    error_log('Database connection not available in send_message.php');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

// Check if JSON decode failed
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
if (!isset($data['receiver_id']) || !isset($data['subject']) || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$sender_id = (int)$_SESSION['user_id'];
$receiver_id = (int)$data['receiver_id'];
$subject = trim($data['subject']);
$message = trim($data['message']);

// Validate input
if (empty($subject) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Subject and message cannot be empty']);
    exit();
}

// Validate receiver ID
if ($receiver_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid recipient ID']);
    exit();
}

// Check if receiver exists
$sql = "SELECT id FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Failed to prepare receiver check query: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param("i", $receiver_id);
if (!$stmt->execute()) {
    error_log('Failed to execute receiver check query: ' . $stmt->error);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
    exit();
}
$stmt->close();

// Get sender information for notification
$sql = "SELECT firstname, lastname FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Failed to prepare sender query: ' . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param("i", $sender_id);
if (!$stmt->execute()) {
    error_log('Failed to execute sender query: ' . $stmt->error);
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$sender_result = $stmt->get_result();
$sender_data = $sender_result->fetch_assoc();
$stmt->close();

if (!$sender_data) {
    error_log('Sender data not found for user_id: ' . $sender_id);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sender information not found']);
    exit();
}

$sender_firstname = $sender_data['firstname'] ?? 'Unknown';
$sender_lastname = $sender_data['lastname'] ?? 'User';
$sender_name = $sender_firstname . ' ' . $sender_lastname;

// Ensure messages table exists (suppress errors - table might already exist)
$create_sql = "CREATE TABLE IF NOT EXISTS messages (
	id INT AUTO_INCREMENT PRIMARY KEY,
	sender_id INT NOT NULL,
	receiver_id INT NOT NULL,
	subject VARCHAR(255) NOT NULL,
	message TEXT NOT NULL,
	is_read TINYINT(1) NOT NULL DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX (sender_id),
	INDEX (receiver_id),
	INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$create_result = $conn->query($create_sql);
if (!$create_result && $conn->error) {
    error_log('Warning: messages table creation query returned error (table may already exist): ' . $conn->error);
}

// Try to add is_read column if it doesn't exist (ignore errors if column already exists)
$alter_sql = "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0";
$alter_result = $conn->query($alter_sql);
if (!$alter_result && $conn->error && strpos($conn->error, 'Duplicate column') === false && strpos($conn->error, 'already exists') === false) {
    error_log('Warning: Failed to add is_read column (may already exist): ' . $conn->error);
}

// Insert message
try {
    // Use CURRENT_TIMESTAMP instead of NOW() for better compatibility
    $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Failed to prepare message insert query: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("iiss", $sender_id, $receiver_id, $subject, $message);

    if (!$stmt->execute()) {
        error_log('Failed to execute message insert: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $stmt->error]);
        exit();
    }

    $message_id = $stmt->insert_id;
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    // Verify message was actually inserted
    if (!$message_id || $affected_rows === 0) {
        error_log('Message insert appeared to succeed but no message_id returned. Affected rows: ' . $affected_rows);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Message was not saved. Please try again.']);
        exit();
    }
} catch (Exception $e) {
    error_log('Exception during message insert: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
    exit();
} catch (Error $e) {
    error_log('Error during message insert: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()]);
    exit();
}

// Create notification for receiver (optional - don't fail if this fails)
try {
    // Ensure notifications table exists
    @$conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (is_read),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $notification_message = "You have received a new message from " . $sender_name;
    
    // Check if title column exists, if not use message only
    $has_title = false;
    try {
        $check_title = $conn->query("SHOW COLUMNS FROM notifications LIKE 'title'");
        if ($check_title && $check_title->num_rows > 0) {
            $has_title = true;
        }
    } catch (Exception $e) {
        // Table might not exist or query failed, assume no title column
        $has_title = false;
    }
    
    if ($has_title) {
        // Table has title column
        $sql = "INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, 'message', 'New Message', ?, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $receiver_id, $notification_message);
            @$stmt->execute(); // Suppress errors
            $stmt->close();
        }
    } else {
        // Table doesn't have title column, use message only
        $sql = "INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'message', ?, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $receiver_id, $notification_message);
            @$stmt->execute(); // Suppress errors
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log('Failed to create notification: ' . $e->getMessage());
    // Continue - notification failure shouldn't block message sending
} catch (Error $e) {
    error_log('Failed to create notification (Error): ' . $e->getMessage());
    // Continue - notification failure shouldn't block message sending
}

// Send email notification (optional - don't fail if this fails)
try {
    $message_preview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
    
    $notification_data = [
        'receiver_id' => $receiver_id,
        'sender_name' => $sender_name,
        'subject' => $subject,
        'message_preview' => $message_preview
    ];
    
    // Send notification via cURL to avoid blocking
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/capsing/public_html/send_message_notification.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout - don't wait too long
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_exec($ch);
    curl_close($ch);
} catch (Exception $e) {
    error_log('Failed to send email notification: ' . $e->getMessage());
    // Continue - email failure shouldn't block message sending
}

echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'message_id' => $message_id]); 