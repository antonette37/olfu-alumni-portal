<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    // Continue with logout even if logging fails
} else {
    // Log the logout if user is logged in
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        try {
            $user_id = $_SESSION['admin_id'] ?? null;
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            // Log the logout action
            $log_sql = "INSERT INTO system_logs (user_id, action_type, description, ip_address) VALUES (?, 'logout', ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            
            if ($log_stmt) {
                $description = "Admin user logged out";
                $log_stmt->bind_param("iss", $user_id, $description, $ip_address);
                $log_stmt->execute();
                $log_stmt->close();
            } else {
                error_log("Failed to prepare logout log statement: " . $conn->error);
            }
        } catch (Exception $e) {
            error_log("Error logging logout: " . $e->getMessage());
        }
    }
}

// Store user info before clearing session
$user_id = $_SESSION['admin_id'] ?? null;
$user_email = $_SESSION['admin_email'] ?? null;

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Close database connection if it exists
if (isset($conn)) {
    $conn->close();
}

// Redirect to homepage
header("Location: al_homepage.php");
exit();
?>
