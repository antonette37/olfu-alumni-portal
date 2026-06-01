<?php
// Enable error logging but don't display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Catch fatal errors and return JSON
register_shutdown_function(function () {
	$error = error_get_last();
	if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
		error_log('Fatal error in send_message_with_attachment.php: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
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
}
catch (Exception $e) {
	error_log('Failed to load config.php: ' . $e->getMessage());
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode(['success' => false, 'message' => 'Configuration error']);
	exit();
}

header('Content-Type: application/json');

// Check database connection
if (!isset($conn) || !$conn) {
	error_log('Database connection not available in send_message_with_attachment.php');
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Database connection error']);
	exit();
}

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit();
}

// Ensure messages table exists
$create_messages_sql = "CREATE TABLE IF NOT EXISTS messages (
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
@$conn->query($create_messages_sql);

// Ensure attachments table exists (idempotent)
@$conn->query("CREATE TABLE IF NOT EXISTS messages_attachments (
	id INT AUTO_INCREMENT PRIMARY KEY,
	message_id INT NOT NULL,
	original_name VARCHAR(255) NOT NULL,
	stored_name VARCHAR(255) NOT NULL,
	mime_type VARCHAR(100) NOT NULL,
	file_size BIGINT NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	INDEX (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$sender_id = (int)$_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($receiver_id <= 0 || $subject === '' || $message === '') {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Missing required fields']);
	exit();
}

// Validate recipient exists
$stmt = $conn->prepare('SELECT id FROM itcp WHERE id = ?');
if (!$stmt) {
	error_log('Failed to prepare recipient check query: ' . $conn->error);
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Database error']);
	exit();
}

$stmt->bind_param('i', $receiver_id);
if (!$stmt->execute()) {
	error_log('Failed to execute recipient check query: ' . $stmt->error);
	$stmt->close();
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Database error']);
	exit();
}

$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();
if (!$exists) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
	exit();
}

$hasFile = isset($_FILES['attachment']) && is_array($_FILES['attachment']) && ($_FILES['attachment']['error'] === UPLOAD_ERR_OK);
$stored = null;

if ($hasFile) {
	$allowedExtensions = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'webp'];
	$maxBytes = 10 * 1024 * 1024; // 10 MB
	$origName = $_FILES['attachment']['name'];
	$tmpPath = $_FILES['attachment']['tmp_name'];
	$fileSize = (int)$_FILES['attachment']['size'];
	$mime = mime_content_type($tmpPath);
	$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
	if ($fileSize <= 0 || $fileSize > $maxBytes) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'File too large (max 10 MB)']);
		exit();
	}
	if (!in_array($ext, $allowedExtensions, true)) {
		http_response_code(400);
		echo json_encode(['success' => false, 'message' => 'File type not allowed']);
		exit();
	}
	// Basic mime allowlist
	$allowedMimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/png', 'image/jpeg', 'image/webp'];
	if ($mime && !in_array($mime, $allowedMimes, true)) {
	// Log or handle unexpected mime if needed
	}
	$subdir = 'uploads/messages/' . date('Y') . '/' . date('m');
	$fullDir = __DIR__ . '/' . $subdir;
	if (!is_dir($fullDir)) {
		if (!@mkdir($fullDir, 0775, true) && !is_dir($fullDir)) {
			error_log('Failed to create upload directory: ' . $fullDir);
			http_response_code(500);
			echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
			exit();
		}
	}
	$rand = bin2hex(random_bytes(8));
	$storedName = $rand . '_' . time() . '.' . $ext;
	$destPath = $fullDir . '/' . $storedName;
	if (!move_uploaded_file($tmpPath, $destPath)) {
		error_log('move_uploaded_file failed from ' . $tmpPath . ' to ' . $destPath);
		http_response_code(500);
		echo json_encode(['success' => false, 'message' => 'Failed to store file']);
		exit();
	}
	$stored = [
		'original_name' => $origName,
		'stored_name' => $subdir . '/' . $storedName,
		'mime_type' => $mime ?: 'application/octet-stream',
		'file_size' => $fileSize,
	];
}

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

$sender_firstname = $sender_data['firstname'] ?? 'Unknown';
$sender_lastname = $sender_data['lastname'] ?? 'User';
$sender_name = $sender_firstname . ' ' . $sender_lastname;

// Insert message
$stmt = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
if (!$stmt) {
	error_log('Failed to prepare message insert query: ' . $conn->error);
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
	exit();
}

$stmt->bind_param('iiss', $sender_id, $receiver_id, $subject, $message);
if (!$stmt->execute()) {
	error_log('Failed to execute message insert: ' . $stmt->error);
	$stmt->close();
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Failed to send message: ' . $stmt->error]);
	exit();
}
$message_id = (int)$stmt->insert_id;
$stmt->close();

if (!$message_id) {
	error_log('Message insert succeeded but no message_id returned');
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'Message was not saved. Please try again.']);
	exit();
}

// Save attachment metadata if present
if ($stored) {
	try {
		$stmt = $conn->prepare('INSERT INTO messages_attachments (message_id, original_name, stored_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)');
		if ($stmt) {
			$stmt->bind_param('isssi', $message_id, $stored['original_name'], $stored['stored_name'], $stored['mime_type'], $stored['file_size']);
			if (!$stmt->execute()) {
				error_log('Failed to save attachment metadata: ' . $stmt->error);
			}
			$stmt->close();
		}
	}
	catch (Exception $e) {
		error_log('Error saving attachment metadata: ' . $e->getMessage());
	// Continue - attachment metadata failure shouldn't block message sending
	}
}

// Create notification (best-effort - don't fail if this fails)
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

	$notification_message = 'You have received a new message from ' . $sender_name;

	// Insert notification without assuming a title column (safer)
	$stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'message', ?, NOW())");
	if ($stmt) {
		$stmt->bind_param('is', $receiver_id, $notification_message);
		@$stmt->execute(); // Suppress errors
		$stmt->close();
	}
}
catch (Exception $e) {
	error_log('Failed to create notification: ' . $e->getMessage());
// Continue - notification failure shouldn't block message sending
}
catch (Error $e) {
	error_log('Failed to create notification (Error): ' . $e->getMessage());
// Continue - notification failure shouldn't block message sending
}

echo json_encode(['success' => true, 'message' => 'Message sent', 'message_id' => $message_id]);
