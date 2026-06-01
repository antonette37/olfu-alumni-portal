<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid alumni ID.";
    header('Location: ad_archives.php');
    exit();
}

$alumni_id = (int)$_GET['id'];

// Get alumni info for logging
$stmt = $conn->prepare("SELECT firstname, lastname, email FROM itcp WHERE id = ? AND status = 'archived'");
if (!$stmt) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header('Location: ad_archives.php');
    exit();
}

$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$alumni = $result->fetch_assoc();
$stmt->close();

if (!$alumni) {
    $_SESSION['error'] = "Archived alumni not found.";
    header('Location: ad_archives.php');
    exit();
}

// Restore the alumni (change status from 'archived' to 'active')
$restore_stmt = $conn->prepare("UPDATE itcp SET status = 'active' WHERE id = ? AND status = 'archived'");
if (!$restore_stmt) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header('Location: ad_archives.php');
    exit();
}

$restore_stmt->bind_param("i", $alumni_id);

if ($restore_stmt->execute()) {
    // Log the restore action
    $admin_id = $_SESSION['admin_id'] ?? 1; // Default admin ID if not set
    $log_stmt = $conn->prepare("INSERT INTO user_logs (user_id, admin_id, action, details, timestamp) VALUES (?, ?, 'restore', ?, NOW())");
    
    if ($log_stmt) {
        $log_details = "Alumni profile restored from archived status";
        $log_stmt->bind_param("iis", $alumni_id, $admin_id, $log_details);
        $log_stmt->execute();
        $log_stmt->close();
    }
    // If logging fails, we still want to proceed with the restore
    
    $_SESSION['success'] = "Alumni profile has been successfully restored.";
} else {
    $_SESSION['error'] = "Failed to restore alumni profile.";
}

$restore_stmt->close();
$conn->close();

// Redirect back to archives page
header('Location: ad_archives.php');
exit();
?>
