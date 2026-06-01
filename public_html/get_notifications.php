<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Initialize response array
$response = [
    'unread_count' => 0,
    'notifications' => []
];

try {
    // Get unread notifications count
    $sql_count = "SELECT COUNT(*) as count FROM notifications WHERE is_read = 0";
    $result_count = $conn->query($sql_count);
    if ($result_count) {
        $row = $result_count->fetch_assoc();
        $response['unread_count'] = $row['count'];
    }

    // Get recent notifications
    $sql_notifications = "SELECT n.*, i.firstname, i.lastname 
                         FROM notifications n 
                         LEFT JOIN itcp i ON n.user_id = i.id 
                         ORDER BY n.created_at DESC 
                         LIMIT 10";
    $result_notifications = $conn->query($sql_notifications);

    if ($result_notifications) {
        while ($row = $result_notifications->fetch_assoc()) {
            $notification = [
                'id' => $row['id'],
                'type' => $row['type'],
                'message' => $row['message'],
                'time' => timeAgo($row['created_at']),
                'read' => (bool)$row['is_read'],
                'link' => getNotificationLink($row['type'], $row['reference_id']),
                'icon' => getNotificationIcon($row['type'])
            ];
            $response['notifications'][] = $notification;
        }
    }
} catch (Exception $e) {
    $response['error'] = 'Error fetching notifications: ' . $e->getMessage();
}

// Helper function to format time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

// Helper function to get notification link
function getNotificationLink($type, $reference_id) {
    switch ($type) {
        case 'job_post':
            return 'ad_content_management.php';
        case 'job_approved':
            return 'al_career.php';
        default:
            return '#';
    }
}

// Helper function to get notification icon
function getNotificationIcon($type) {
    switch ($type) {
        case 'job_post':
            return 'fa-briefcase';
        case 'job_approved':
            return 'fa-check-circle';
        default:
            return 'fa-bell';
    }
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 