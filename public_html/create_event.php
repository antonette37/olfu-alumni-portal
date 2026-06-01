<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

// Check if user is logged in as coordinator
if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Get form data
$title = $_POST['title'];
$type = $_POST['type'];
$description = $_POST['description'];
$event_date = $_POST['start_datetime'];
$end_date = $_POST['end_datetime'];
$venue = $_POST['venue'];
$audience = $_POST['audience'];
$created_by = $_POST['created_by'];
$status = 'published'; // Set initial status as published

// Prepare and execute the insert query
$stmt = $conn->prepare("INSERT INTO events (title, type, description, event_date, end_date, venue, audience, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssssis", $title, $type, $description, $event_date, $end_date, $venue, $audience, $created_by, $status);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Event created successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error creating event: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?> 