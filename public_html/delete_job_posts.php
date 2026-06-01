<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get the posted JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if job_ids are provided and is an array
if (!isset($data['job_ids']) || !is_array($data['job_ids']) || empty($data['job_ids'])) {
    echo json_encode(['success' => false, 'error' => 'No job IDs provided.']);
    exit();
}

$job_ids = $data['job_ids'];

// Sanitize and validate job IDs
$valid_job_ids = array_map('intval', $job_ids); // Ensure they are integers
$valid_job_ids = array_filter($valid_job_ids, function($id) { return $id > 0; }); // Filter out invalid IDs

if (empty($valid_job_ids)) {
    echo json_encode(['success' => false, 'error' => 'Invalid job IDs provided.']);
    $conn->close();
    exit();
}

// Create a comma-separated string of IDs for the SQL query
$ids_string = implode(',', $valid_job_ids);

// Prepare and execute the delete query
$sql = "DELETE FROM jobs WHERE id IN ($ids_string)";

if ($conn->query($sql) === TRUE) {
    $deleted_count = $conn->affected_rows;
    echo json_encode(['success' => true, 'message' => $deleted_count . ' job post(s) deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error deleting job posts: ' . $conn->error]);
}

$conn->close();
?> 