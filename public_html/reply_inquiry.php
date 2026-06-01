<?php
session_start();
require_once 'db_config.php';
require_once 'mail.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
	header('Location: al_login.php');
	exit();
}

$conn = getDBConnection();

$to = filter_var($_POST['to'] ?? '', FILTER_SANITIZE_EMAIL);
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');
$inquiryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
	header('Location: view_inquiry.php?id=' . $inquiryId . '&status=invalid');
	exit();
}

// Resolve alumni user by email for inbox entry
$receiverId = null;
if ($stmtU = $conn->prepare('SELECT id FROM itcp WHERE email = ? LIMIT 1')) {
	$stmtU->bind_param('s', $to);
	if ($stmtU->execute()) {
		$resU = $stmtU->get_result();
		if ($resU && $resU->num_rows > 0) {
			$receiverId = (int)$resU->fetch_assoc()['id'];
		}
	}
	$stmtU->close();
}

// Ensure messages table exists and has is_read column
@$conn->query("CREATE TABLE IF NOT EXISTS messages (
	id INT AUTO_INCREMENT PRIMARY KEY,
	sender_id INT NOT NULL,
	receiver_id INT NOT NULL,
	subject VARCHAR(255) NOT NULL,
	message TEXT NOT NULL,
	is_read TINYINT(1) NOT NULL DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Add is_read column if table already existed without it
@$conn->query("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");

// Insert reply into alumni's message box (unread)
if ($receiverId !== null) {
	$senderId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0; // 0/system if not mapped
	if ($stmtM = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, subject, message, is_read) VALUES (?, ?, ?, ?, 0)')) {
		$rawBody = $body;
		$stmtM->bind_param('iiss', $senderId, $receiverId, $subject, $rawBody);
		$stmtM->execute();
		$stmtM->close();
	}
}

// Update inquiry status to In Progress and mark as read
if ($inquiryId > 0) {
	if ($stmtQ = $conn->prepare('UPDATE contact_messages SET status = "In Progress", is_read = 1 WHERE id = ?')) {
		$stmtQ->bind_param('i', $inquiryId);
		$stmtQ->execute();
		$stmtQ->close();
	}
}

// Send email notification to the alumni
$emailHtml = '<div style="font-family:Arial,sans-serif;max-width:640px;margin:0 auto;">'
	. '<h2 style="color:#047857;margin-bottom:8px;">Response from Admin</h2>'
	. '<p style="color:#111;margin:0 0 12px 0;">Subject: ' . htmlspecialchars($subject) . '</p>'
	. '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;white-space:pre-wrap;">'
	. nl2br(htmlspecialchars($body))
	. '</div>'
	. '<p style="color:#6b7280;font-size:12px;margin-top:14px;">This is an automated notification from the OLFU Alumni System.</p>'
	. '</div>';

@sendEmail($to, ($subject !== '' ? $subject : 'Reply to your inquiry'), $emailHtml);

$conn->close();
header('Location: view_inquiry.php?id=' . $inquiryId . '&status=sent#content');
exit();
