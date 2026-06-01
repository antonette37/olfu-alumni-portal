<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid alumni ID.";
    header('Location: ad_alumnidirectory.php');
    exit();
}

$alumni_id = (int)$_GET['id'];

// First, get the alumni's photo filename to delete it
$sql = "SELECT photo FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// Delete the alumni record
$sql = "DELETE FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $alumni_id);

if ($stmt->execute()) {
    // If deletion was successful, delete the photo file if it exists
    if ($row && $row['photo'] && file_exists($row['photo'])) {
        unlink($row['photo']);
    }

    $_SESSION['success'] = "Alumni profile has been successfully deleted.";
}
else {
    $_SESSION['error'] = "Error deleting alumni profile: " . $conn->error;
}

$stmt->close();
$conn->close();

// Redirect back to alumni directory
header('Location: ad_alumnidirectory.php');
exit();