<?php
session_start();
require_once 'db_config.php';

// Simple admin gate (adjust based on your admin session flag)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

$conn = getDBConnection();

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Get user details before archiving
    $user_sql = "SELECT firstname, lastname, email FROM itcp WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    
    if ($user_data) {
        // Update user status to 'archived'
        $update_sql = "UPDATE itcp SET status = 'archived' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        
        if ($update_stmt->execute()) {
            // Log archive action
            $log_stmt = $conn->prepare("INSERT INTO user_logs (user_id, activity_type, activity_description, ip_address) VALUES (?, ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $activity = 'archive_user';
            $desc = "Admin archived user: {$user_data['firstname']} {$user_data['lastname']} ({$user_data['email']})";
            if ($log_stmt) { 
                $log_stmt->bind_param('isss', $user_id, $activity, $desc, $ip); 
                $log_stmt->execute(); 
                $log_stmt->close(); 
            }
            
            // Redirect back with success message
            header("Location: ad_user_management.php?status_msg=archived");
            exit();
        } else {
            header("Location: ad_user_management.php?error=archive_failed");
            exit();
        }
    } else {
        header("Location: ad_user_management.php?error=user_not_found");
        exit();
    }
} else {
    header("Location: ad_user_management.php");
    exit();
}
?>
