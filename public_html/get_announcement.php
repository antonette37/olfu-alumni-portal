<?php
session_start();
require_once 'db_config.php';

// Set header to JSON
header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if announcement ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Announcement ID is required']);
    exit;
}

$announcement_id = intval($_GET['id']);

try {
    // Get database connection
    $conn = getDBConnection();
    
    // Check if connection is valid
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Fetch announcement details
    $sql = "SELECT id, title, content, created_at, image FROM announcements WHERE id = ? AND status = 'published'";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . ($conn->error ?? 'Unknown error'));
    }
    
    $stmt->bind_param("i", $announcement_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Announcement not found']);
        exit;
    }

    $announcement = $result->fetch_assoc();
    
    // Clean content for display (handle newlines)
    if (isset($announcement['content'])) {
        $announcement['content'] = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $announcement['content']);
        $announcement['content'] = stripslashes($announcement['content']);
    }
    
    // Return announcement data as JSON
    echo json_encode($announcement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_announcement.php: " . $e->getMessage());
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} 