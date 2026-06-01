<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

// Keep the 30-day device verification cookie (olfu_dev_ver) so OTP is not required again
// until the cookie expires or the user signs in from a new browser/device.

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    // Continue with logout even if logging fails
} else {
    // Log the logout if user is logged in
    if (isset($_SESSION['user_id'])) {
        try {
            require_once 'user_logging.php';
            $user_id = $_SESSION['user_id'];
            $username = ($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '');
            logUserLogout($conn, $user_id, trim($username));
        } catch (Exception $e) {
            error_log("Error logging logout: " . $e->getMessage());
        }
    }
}

// Store user info before clearing session
$user_id = $_SESSION['user_id'] ?? null;
$user_email = $_SESSION['email'] ?? null;

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
