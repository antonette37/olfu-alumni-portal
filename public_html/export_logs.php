<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

require_once 'db_config.php';
$conn = getDBConnection();

// Get filter parameters
$action_type = isset($_GET['action_type']) ? $conn->real_escape_string($_GET['action_type']) : '';
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build the query (without pagination - get all records)
$sql = "SELECT l.id, l.user_id, l.action_type, l.description, l.ip_address, l.created_at,
               i.firstname, i.lastname, i.email 
        FROM system_logs l 
        LEFT JOIN itcp i ON l.user_id = i.id 
        WHERE 1=1";

if (!empty($action_type)) {
    $sql .= " AND l.action_type = '$action_type'";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(l.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $sql .= " AND DATE(l.created_at) <= '$date_to'";
}

if (!empty($search)) {
    $sql .= " AND (i.firstname LIKE '%$search%' 
              OR i.lastname LIKE '%$search%' 
              OR i.email LIKE '%$search%' 
              OR l.description LIKE '%$search%')";
}

// Order by most recent first
$sql .= " ORDER BY l.created_at DESC";

$result = $conn->query($sql);
$logs_data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs_data[] = $row;
    }
}

$conn->close();

// Generate filename
$filename = "system_logs_" . date('Y-m-d_His') . ".csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 (helps Excel recognize encoding)
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write header row
fputcsv($output, [
    'ID',
    'User ID',
    'User Name',
    'User Email',
    'Action Type',
    'Description',
    'IP Address',
    'Date & Time'
]);

// Write data rows
foreach ($logs_data as $log) {
    $fullname = trim(($log['firstname'] ?? '') . ' ' . ($log['lastname'] ?? ''));
    if (empty($fullname)) {
        $fullname = 'N/A';
    }

    fputcsv($output, [
        $log['id'] ?? '',
        $log['user_id'] ?? 'N/A',
        $fullname,
        $log['email'] ?? 'N/A',
        ucfirst(str_replace('_', ' ', $log['action_type'] ?? '')),
        $log['description'] ?? '',
        $log['ip_address'] ?? 'N/A',
        date('Y-m-d H:i:s', strtotime($log['created_at'] ?? ''))
    ]);
}

fclose($output);
exit;
