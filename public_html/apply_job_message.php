<?php
// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once 'config.php';

// Verify database connection
if (!isset($conn) || !$conn) {
    error_log("apply_job_message: Database connection not available");
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'db_connection', 'message' => 'Database connection error. Please try again.']);
        exit();
    }
    header('Location: al_career.php?error=db_connection');
    exit();
}

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Function to send JSON response and exit
function send_json_response($data, $is_ajax) {
    if ($is_ajax) {
        ob_clean(); // Clear any unexpected output
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(['success' => false, 'error' => 'not_logged_in', 'redirect' => 'al_login.php'], $is_ajax);
    header('Location: al_login.php?redirect=' . urlencode('apply_job_message.php?' . http_build_query($_GET)));
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

if ($job_id <= 0) {
    send_json_response(['success' => false, 'error' => 'invalid_job'], $is_ajax);
    header('Location: al_career.php?error=invalid_job');
    exit();
}

// Fetch job info and poster
$job_sql = "SELECT j.id, j.title, j.user_id, u.email, u.firstname, u.lastname
            FROM jobs j
            LEFT JOIN itcp u ON j.user_id = u.id
            WHERE j.id = ? AND j.status = 'active'";
$stmt = $conn->prepare($job_sql);
if (!$stmt) {
    $db_error = $conn->error ?: 'Unknown database error';
    error_log("apply_job_message: prepare failed: " . $db_error);
    send_json_response(['success' => false, 'error' => 'db_error', 'message' => 'Failed to fetch job information.'], $is_ajax);
    header('Location: al_career.php?error=db');
    exit();
}
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    send_json_response(['success' => false, 'error' => 'job_not_found', 'message' => 'Job not found or no longer available.'], $is_ajax);
    header('Location: al_career.php?error=job_not_found');
    exit();
}

$receiver_id = (int)$job['user_id'];
if ($receiver_id === $current_user_id) {
    send_json_response(['success' => false, 'error' => 'own_job', 'message' => 'You cannot apply to your own job posting.'], $is_ajax);
    header('Location: al_career.php?error=own_job');
    exit();
}

// Check if user has already applied
@$conn->query("CREATE TABLE IF NOT EXISTS job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    applicant_id INT NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_application (job_id, applicant_id),
    INDEX (job_id),
    INDEX (applicant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$check_applied = $conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND applicant_id = ?");
if (!$check_applied) {
    $db_error = $conn->error ?: 'Unknown database error';
    error_log("apply_job_message: check_applied prepare failed: " . $db_error);
    // Continue anyway - we'll try to insert and let the unique constraint handle duplicates
} else {
    $check_applied->bind_param("ii", $job_id, $current_user_id);
    if ($check_applied->execute()) {
        $applied_result = $check_applied->get_result();
        if ($applied_result && $applied_result->num_rows > 0) {
            // Already applied
            $check_applied->close();
            send_json_response(['success' => true, 'already_applied' => true, 'receiver_id' => $receiver_id], $is_ajax);
            header('Location: al_messages.php?to=' . $receiver_id);
            exit();
        }
    } else {
        error_log("apply_job_message: check_applied execute failed: " . $check_applied->error);
    }
    if ($check_applied) {
        $check_applied->close();
    }
}

// Ensure messages table exists (best-effort)
$create_table_sql = "CREATE TABLE IF NOT EXISTS messages (
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

$create_result = $conn->query($create_table_sql);
if (!$create_result && $conn->error) {
    error_log("apply_job_message: messages table creation failed: " . $conn->error);
    // Continue anyway - table might already exist with different structure
}

// Check if message column exists, if not add it
$check_message_col = $conn->query("SHOW COLUMNS FROM messages LIKE 'message'");
if (!$check_message_col || $check_message_col->num_rows === 0) {
    // Try to add message column
    $alter_message = $conn->query("ALTER TABLE messages ADD COLUMN message TEXT NOT NULL");
    if (!$alter_message && $conn->error) {
        error_log("apply_job_message: Failed to add message column: " . $conn->error);
    }
}

// Try to add is_read column if it doesn't exist (ignore errors if column already exists)
$alter_sql = "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0";
$alter_result = $conn->query($alter_sql);
if (!$alter_result && $conn->error && strpos($conn->error, 'Duplicate column') === false && strpos($conn->error, 'already exists') === false) {
    error_log("apply_job_message: Failed to add is_read column: " . $conn->error);
}

$subject = "Job Interest: " . ($job['title'] ?: 'Job Posting');
$job_title = $job['title'] ?: 'this job posting';

// Set the message body with job title
$body = "Hi! I am interested in the \"" . htmlspecialchars($job_title, ENT_QUOTES, 'UTF-8') . "\" position. How can I apply?";

error_log("apply_job_message: Message body being sent: [" . $body . "]");

try {
    // Set the message body - this is the automatic message sent to job poster
    // Include the job title so the poster knows which job the applicant is referring to
    $job_title = $job['title'] ?: 'this job posting';
    $message_body = "Hi! I am interested in the \"" . htmlspecialchars($job_title, ENT_QUOTES, 'UTF-8') . "\" position. How can I apply?";
    
    // Ensure message body is not empty
    if (empty(trim($message_body))) {
        $job_title = $job['title'] ?: 'this job posting';
        $message_body = "Hi! I am interested in the \"" . htmlspecialchars($job_title, ENT_QUOTES, 'UTF-8') . "\" position. How can I apply?";
    }
    
    error_log("apply_job_message: About to insert message - subject: [" . $subject . "], message_body: [" . $message_body . "]");
    
    // Use the 'message' column directly (this is the standard column name in the messages table)
    // If the column doesn't exist, the table creation above should have created it
    $sql = "INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
    
    error_log("apply_job_message: SQL query: " . $sql);
    
    $insert = $conn->prepare($sql);
    if (!$insert) {
        $db_error = $conn->error ?: 'Unknown database error';
        error_log("apply_job_message: insert prepare failed: " . $db_error . " | SQL: " . $sql);
        // Always show the actual error for debugging
        $error_message = 'Failed to prepare database query. Error: ' . $db_error;
        throw new Exception($error_message);
    }
    
    // Bind 4 parameters (CURRENT_TIMESTAMP is a literal, not a placeholder)
    $bind_result = $insert->bind_param("iiss", $current_user_id, $receiver_id, $subject, $message_body);
    if (!$bind_result) {
        $db_error = $insert->error ?: 'Unknown database error';
        error_log("apply_job_message: bind_param failed: " . $db_error);
        $insert->close();
        throw new Exception('Failed to bind parameters. Please try again.');
    }
    
    if (!$insert->execute()) {
        $db_error = $insert->error ?: 'Unknown database error';
        error_log("apply_job_message: insert execute failed: " . $db_error . " | User ID: " . $current_user_id . " | Receiver ID: " . $receiver_id . " | Message: [" . $message_body . "]");
        $insert->close();
        // Check for common errors and provide user-friendly messages
        if (strpos($db_error, 'Duplicate entry') !== false) {
            throw new Exception('You have already applied for this job.');
        } elseif (strpos($db_error, 'Foreign key constraint') !== false) {
            throw new Exception('Invalid user or job information. Please refresh the page and try again.');
        } else {
            throw new Exception('Failed to send application. Please try again or contact support if the problem persists.');
        }
    }
    
    // Verify the message was inserted correctly
    $inserted_id = $conn->insert_id;
    error_log("apply_job_message: Message inserted successfully with ID: " . $inserted_id);
    
    // Double-check the message was saved correctly
    $verify_stmt = $conn->prepare("SELECT message FROM messages WHERE id = ?");
    if ($verify_stmt) {
        $verify_stmt->bind_param("i", $inserted_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        if ($verify_row = $verify_result->fetch_assoc()) {
            $saved_message = $verify_row['message'];
            error_log("apply_job_message: Verified saved message: [" . $saved_message . "]");
            if (empty(trim($saved_message))) {
                error_log("apply_job_message: WARNING - Saved message is empty! Attempting to update...");
                // Try to update the message
                $update_stmt = $conn->prepare("UPDATE messages SET message = ? WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("si", $message_body, $inserted_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    error_log("apply_job_message: Updated message with ID " . $inserted_id);
                }
            }
        }
        $verify_stmt->close();
    }
    
    $insert->close();
} catch (Exception $e) {
    error_log("apply_job_message: Exception during message insert: " . $e->getMessage());
    send_json_response(['success' => false, 'error' => 'message_failed', 'message' => $e->getMessage()], $is_ajax);
    header('Location: al_career.php?error=message_failed');
    exit();
}

// Record the application
$record_application = $conn->prepare("INSERT INTO job_applications (job_id, applicant_id, applied_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE applied_at = applied_at");
if ($record_application) {
    $record_application->bind_param("ii", $job_id, $current_user_id);
    $record_application->execute();
    $record_application->close();
}

// Create notification for receiver (best-effort)
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

$notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, created_at) VALUES (?, 'message', ?, NOW())");
if ($notif_stmt) {
    $notif_msg = "A user is interested in your job post: " . ($job['title'] ?: 'Job Posting');
    $notif_stmt->bind_param("is", $receiver_id, $notif_msg);
    $notif_stmt->execute();
    $notif_stmt->close();
}

// Return success response
send_json_response(['success' => true, 'receiver_id' => $receiver_id], $is_ajax);

// Redirect to messages page with recipient preselected
header('Location: al_messages.php?to=' . $receiver_id);
exit();

