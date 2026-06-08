<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper headers for JSON response
header('Content-Type: application/json');

session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate input data
$milestone_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$date_achieved = isset($_POST['date_achieved']) ? trim($_POST['date_achieved']) : '';
$visibility = isset($_POST['visibility']) ? trim($_POST['visibility']) : 'Public';


// Validate required fields
if (!$milestone_id || !$title || !$date_achieved) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate visibility value
$allowed_visibility = ['Public', 'Private', 'Admin Only'];
if (!in_array($visibility, $allowed_visibility)) {
    echo json_encode(['success' => false, 'message' => 'Invalid visibility setting']);
    exit();
}

try {
    // First verify that the milestone belongs to the current user
    $check_sql = "SELECT alumni_id FROM career_milestones WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $milestone_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $milestone = $result->fetch_assoc();

    if (!$milestone || $milestone['alumni_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }

    // Update the milestone
    $update_sql = "UPDATE career_milestones SET 
                   title = ?, 
                   description = ?, 
                   date_achieved = ?,
                   visibility = ?,
                   updated_at = CURRENT_TIMESTAMP
                   WHERE id = ? AND alumni_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssii", $title, $description, $date_achieved, $visibility, $milestone_id, $_SESSION['user_id']);
    
    if ($update_stmt->execute()) {
        // Log the update in version history
        $history_sql = "INSERT INTO career_history (alumni_id, job_title, company, industry, start_date, description) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $history_stmt = $conn->prepare($history_sql);
        $company = "Updated milestone"; // Default value since we don't have company info
        $industry = "Career Update"; // Default value for industry
        $history_stmt->bind_param("isssss", 
            $_SESSION['user_id'],
            $title,
            $company,
            $industry,
            $date_achieved,
            $description
        );
        $history_stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Milestone updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update milestone: ' . $conn->error]);
    }

} catch (Exception $e) {
    error_log("Error updating milestone: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the milestone: ' . $e->getMessage()]);
}

// Close database connection
$conn->close();
?> 