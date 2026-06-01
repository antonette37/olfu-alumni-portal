<?php
session_start();
require_once 'db_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$conn = getDBConnection();
$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    echo json_encode(['alumni' => [], 'inquiries' => [], 'events' => [], 'system' => []]);
    exit;
}

$searchTerm = '%' . $conn->real_escape_string($query) . '%';
$results = [
    'alumni' => [],
    'inquiries' => [],
    'events' => [],
    'system' => []
];

try {
    // Search alumni
    $alumni_sql = "SELECT id, firstname, lastname, email, program, year_graduated 
                   FROM itcp 
                   WHERE CONCAT(firstname, ' ', lastname) LIKE ? 
                   OR email LIKE ? 
                   OR program LIKE ?
                   ORDER BY firstname, lastname 
                   LIMIT 5";
    $stmt = $conn->prepare($alumni_sql);
    if ($stmt) {
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $alumni_result = $stmt->get_result();
        while ($row = $alumni_result->fetch_assoc()) {
            $results['alumni'][] = [
                'title' => $row['firstname'] . ' ' . $row['lastname'],
                'subtitle' => $row['program'] . ' (' . $row['year_graduated'] . ')',
                'url' => 'ad_alumnirecord.php?id=' . $row['id']
            ];
        }
        $stmt->close();
    }

    // Search contact messages/inquiries
    $inquiries_sql = "SELECT id, name, email, subject, message, status 
                      FROM contact_messages 
                      WHERE name LIKE ? 
                      OR email LIKE ? 
                      OR subject LIKE ? 
                      OR message LIKE ?
                      ORDER BY submitted_at DESC 
                      LIMIT 5";
    $stmt = $conn->prepare($inquiries_sql);
    if ($stmt) {
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $inquiries_result = $stmt->get_result();
        while ($row = $inquiries_result->fetch_assoc()) {
            $subject_text = !empty($row['subject']) ? $row['subject'] : substr($row['message'], 0, 60);
            $subject_text = strlen($subject_text) > 60 ? substr($subject_text, 0, 60) . '...' : $subject_text;
            $message_preview = strlen($row['message']) > 100 ? substr($row['message'], 0, 100) . '...' : $row['message'];
            $results['inquiries'][] = [
                'title' => $subject_text,
                'subtitle' => 'From ' . $row['name'],
                'url' => 'ad_contactmessages.php'
            ];
        }
        $stmt->close();
    }

    // Search events
    $events_sql = "SELECT id, title, description, event_date, location 
                   FROM events 
                   WHERE title LIKE ? 
                   OR description LIKE ? 
                   OR location LIKE ?
                   ORDER BY event_date DESC 
                   LIMIT 5";
    $stmt = $conn->prepare($events_sql);
    if ($stmt) {
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $events_result = $stmt->get_result();
        while ($row = $events_result->fetch_assoc()) {
            $results['events'][] = [
                'title' => $row['title'],
                'subtitle' => date('M d, Y', strtotime($row['event_date'])) . ' - ' . $row['location'],
                'url' => 'ad_events.php?id=' . $row['id']
            ];
        }
        $stmt->close();
    }

    // Search system pages/features
    $system_pages = [
        ['title' => 'User Management', 'subtitle' => 'Manage users and permissions', 'url' => 'ad_user_management.php'],
        ['title' => 'System Logs', 'subtitle' => 'View system activity logs', 'url' => 'ad_logs.php'],
        ['title' => 'Reports', 'subtitle' => 'Generate system reports', 'url' => 'ad_reports.php'],
        ['title' => 'Content Management', 'subtitle' => 'Manage website content', 'url' => 'ad_content_management.php'],
        ['title' => 'Announcements', 'subtitle' => 'Manage announcements', 'url' => 'ad_announcements.php']
    ];

    foreach ($system_pages as $page) {
        if (stripos($page['title'], $query) !== false || stripos($page['subtitle'], $query) !== false) {
            $results['system'][] = $page;
        }
    }

} catch (Exception $e) {
    error_log("Admin search error: " . $e->getMessage());
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($results);
?>
