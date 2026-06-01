<?php
require_once 'db_config.php';
$conn = getDBConnection();
require_once 'mail.php';
require_once 'admin_email_notifications.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$host = "localhost";
$dbname = "alumni_system";
$username = "root"; 
$password = "";    

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Debug: Log received POST data
    error_log("Contact form POST data: " . print_r($_POST, true));
    
    // Fix field name mapping to match the form
    $name = htmlspecialchars(trim($_POST["firstname"] ?? $_POST["name"] ?? ""));
    // Always use the logged-in user's email from session if available
    $email = '';
    if (!empty($_SESSION['user_id'])) {
        // Resolve user email from DB for safety
        $uid = (int)$_SESSION['user_id'];
        $dbEmail = '';
        if ($stmtU = $conn->prepare("SELECT email FROM itcp WHERE id = ?")) {
            $stmtU->bind_param("i", $uid);
            if ($stmtU->execute()) {
                $resU = $stmtU->get_result();
                $rowU = $resU ? $resU->fetch_assoc() : null;
                if ($rowU && !empty($rowU['email'])) $dbEmail = $rowU['email'];
            }
            $stmtU->close();
        }
        $email = htmlspecialchars(trim($dbEmail));
    } else {
        $email = htmlspecialchars(trim($_POST["mail"] ?? $_POST["email"] ?? ""));
    }
    $subject = htmlspecialchars(trim($_POST["subject"] ?? ""));
    $message = htmlspecialchars(trim($_POST["message"] ?? ""));
    
    // Debug: Log processed data
    error_log("Processed contact data - Name: $name, Email: $email, Subject: $subject");
    
    // Keep subject and message separate for admin inbox + detail view

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: al_contact.php?status=error");
        exit();
    }

    if (empty($name) || empty($email) || empty($message)) {
        header("Location: al_contact.php?status=error");
        exit();
    }

    // Ensure the contact_messages table exists with proper structure
    $create_table_sql = "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) DEFAULT NULL,
        message TEXT NOT NULL,
        status ENUM('New','In Progress','Resolved','Spam') NOT NULL DEFAULT 'New',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($create_table_sql)) {
        error_log("Failed to create contact_messages table: " . $conn->error);
        header("Location: al_contact.php?status=error");
        exit();
    }

    // Ensure schema columns exist (in case table already existed)
    @$conn->query("ALTER TABLE contact_messages ADD COLUMN subject VARCHAR(255) NULL");
    @$conn->query("ALTER TABLE contact_messages ADD COLUMN status ENUM('New','In Progress','Resolved','Spam') NOT NULL DEFAULT 'New'");
    @$conn->query("ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
    @$conn->query("ALTER TABLE contact_messages MODIFY COLUMN submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, status, is_read) VALUES (?, ?, ?, ?, 'New', 0)");
    
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        header("Location: al_contact.php?status=error");
        exit();
    }
    
    $stmt->bind_param("ssss", $name, $email, $subject, $message);

    if ($stmt->execute()) {
        // Send email alert to admin/support
        $admin_subject = 'New Alumni Inquiry: ' . ($subject ? $subject : 'Contact Form Submission');
        $admin_body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
              . '<h2 style="color: #047857; border-bottom: 2px solid #047857; padding-bottom: 10px;">New Alumni Inquiry</h2>'
              . '<p>You have received a new inquiry from the Alumni Contact Us form.</p>'
              . '<div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">'
              . '<p><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>'
              . '<p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>'
              . ($subject ? '<p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>' : '')
              . '<p><strong>Message:</strong></p>'
              . '<div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #047857;">'
              . nl2br(htmlspecialchars($message))
              . '</div>'
              . '</div>'
              . '<div style="background: #ecfdf5; padding: 15px; border-radius: 8px; margin: 20px 0;">'
              . '<p style="margin: 0;"><strong>Action Required:</strong></p>'
              . '<p style="margin: 5px 0 0 0;">Log in to the Admin panel → Communication & Support → Manage Inquiries to respond to this inquiry.</p>'
              . '</div>'
              . '<p style="color: #6b7280; font-size: 12px;">This is an automated notification from the OLFU Alumni System.</p>'
              . '</div>';
        
        // Send admin notification email using the helper function
        $inquiryData = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message
        ];
        sendAdminNewInquiryNotification($inquiryData);
        
        // Log the inquiry for debugging
        error_log("New contact inquiry received from: " . $name . " (" . $email . ")");
        
        $stmt->close();
        header("Location: al_my_tickets.php?status=success");
        exit();
    } else {
        error_log("Failed to insert contact message: " . $stmt->error);
        $stmt->close();
        header("Location: al_contact.php?status=error");
        exit();
    }
} else {
    header("Location: al_contact.php");
}

$conn->close();
?>
