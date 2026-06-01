<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if coordinator is logged in
if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    error_log('Unauthorized access attempt - Session data: ' . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$raw_data = file_get_contents('php://input');
error_log('Received raw data: ' . $raw_data);
$data = json_decode($raw_data, true);

if (!isset($data['id'])) {
    error_log('Missing user ID in request');
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit();
}

// Database connection for web hosting
$host = 'localhost'; // Your web hosting database host
$user = 'u123456789_caps'; // Your web hosting database username
$pass = 'your_password'; // Your web hosting database password
$db = 'u123456789_caps'; // Your web hosting database name

require_once 'db_config.php';
$conn = getDBConnection();

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Diagnostic: Check table structure
$table_info = $conn->query("DESCRIBE itcp");
error_log('Table structure:');
while ($row = $table_info->fetch_assoc()) {
    error_log(print_r($row, true));
}

// First get user details for email notification
$sql = "SELECT id, firstname, lastname, email, status FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $data['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log('User not found with ID: ' . $data['id']);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$user_data = $result->fetch_assoc();
error_log('Current user status: ' . $user_data['status']);

// Diagnostic: Check if status column exists and its current value
$check_sql = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'itcp' AND COLUMN_NAME = 'status'";
$check_result = $conn->query($check_sql);
if ($check_result->num_rows === 0) {
    error_log('Status column does not exist in the table!');
    echo json_encode(['success' => false, 'error' => 'Database structure error: status column missing']);
    exit();
}

// Update user status to approved
$sql = "UPDATE itcp SET status = 'approved' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $data['id']);

if ($stmt->execute()) {
    error_log('Successfully updated user status to approved');
    
    // Verify the update was successful
    $verify_sql = "SELECT status FROM itcp WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $data['id']);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_data = $verify_result->fetch_assoc();
    
    error_log('Verification - New status: ' . $verify_data['status']);
    
    if ($verify_data['status'] !== 'approved') {
        error_log('Status verification failed - Expected: approved, Got: ' . $verify_data['status']);
        echo json_encode(['success' => false, 'error' => 'Status update verification failed']);
        exit();
    }
    
    // Include mail sending function
    require 'mail.php';

    // Send email notification
    $recipient_email = $user_data['email'];
    $subject = 'Account Approved: Welcome to OLFU Alumni Portal';
    $body = "
        <h2>Welcome to OLFU Alumni Portal!</h2>
        <p>Dear {$user_data['firstname']} {$user_data['lastname']},</p>
        <p>Your registration has been approved. You can now log in to your account and access all features of the OLFU Alumni Portal.</p>
        <p style='margin: 24px 0;'>
            <a href='https://your-domain.com/caps/al_homepage.php' style='display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;'>Login to Alumni Portal</a>
        </p>
        <p>Best regards,<br>OLFU Alumni Affairs</p>
    ";

    if (sendEmail($recipient_email, $subject, $body)) {
        error_log('Successfully sent approval email to: ' . $recipient_email);
        echo json_encode(['success' => true, 'message' => 'User approved and email sent successfully']);
    } else {
        error_log('Failed to send approval email to: ' . $recipient_email);
        echo json_encode(['success' => false, 'error' => 'Failed to send email notification']);
    }
} else {
    error_log('Failed to update user status: ' . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Failed to update user status']);
}

$conn->close();
?> 