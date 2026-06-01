<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['fields'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$id = (int)$data['id'];
$fields = $data['fields'];

if ($id <= 0 || empty($fields)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// Build the update query dynamically
$update_fields = [];
$types = "";
$values = [];

foreach ($fields as $field => $value) {
    // Validate field name to prevent SQL injection
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
        continue;
    }
    
    $update_fields[] = "$field = ?";
    $types .= "s"; // Assuming all fields are strings
    $values[] = $value;
}

if (empty($update_fields)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No valid fields to update']);
    exit();
}

// Add the ID to the values array
$values[] = $id;
$types .= "i";

$query = "UPDATE itcp SET " . implode(", ", $update_fields) . " WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    // Log the update
    $log_query = "INSERT INTO system_logs (action, table_name, record_id, user_id, details) 
                  VALUES ('update', 'itcp', ?, ?, 'Alumni record updated')";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("ii", $id, $_SESSION['coordinator_id']);
    $log_stmt->execute();
    $log_stmt->close();

    // Send notification to alumni
    $notification_query = "INSERT INTO notifications (user_id, type, message, created_at) 
                          SELECT id, 'update', 'Your alumni record has been updated by the coordinator.', NOW() 
                          FROM itcp WHERE id = ?";
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param("i", $id);
    $notification_stmt->execute();
    $notification_stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating record']);
}

$stmt->close();
$conn->close();
?> 