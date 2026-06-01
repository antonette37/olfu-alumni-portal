<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

// Check if user is logged in (either alumni or coordinator)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['coordinator_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

// Get the record
$id = intval($_GET['id']);
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'alumni' => $row]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Record not found']);
}

$conn->close();
?> 