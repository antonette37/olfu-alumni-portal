<?php
// Set proper headers and start output buffering to prevent any HTML output
header('Content-Type: application/json');
ob_start();

// Suppress any warnings/notices that might interfere with JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start session to track admin visits
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $count_sql = "SELECT COUNT(*) as pending_count FROM itcp WHERE LOWER(TRIM(COALESCE(status,''))) = 'pending'";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute();
    $count_result = mysqli_stmt_fetch_assoc_compat($count_stmt);
    $pending_count = (int)($count_result['pending_count'] ?? 0);

    $requests_sql = "SELECT id, firstname, lastname, email, date_joined FROM itcp WHERE LOWER(TRIM(COALESCE(status,''))) = 'pending' ORDER BY date_joined DESC LIMIT 5";
    $requests_stmt = $conn->prepare($requests_sql);
    
    $requests_stmt->execute();
    $requests = [];
    foreach (mysqli_stmt_fetch_all_assoc_compat($requests_stmt) as $row) {
        $requests[] = [
            'id' => $row['id'],
            'name' => trim($row['firstname'] . ' ' . $row['lastname']),
            'email' => $row['email'],
            'time' => date('M d, Y H:i', strtotime($row['date_joined']))
        ];
    }

    $response = [
        'pending_count' => (int)$pending_count,
        'requests' => $requests,
        'last_visit' => null
    ];

    // Clear any output buffer content and send clean JSON
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    // Clear any output buffer content and send error JSON
    ob_clean();
    echo json_encode(['error' => $e->getMessage(), 'pending_count' => 0, 'requests' => []]);
}
?>
