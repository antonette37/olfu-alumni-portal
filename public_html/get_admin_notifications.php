<?php
session_start();
require_once 'db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$conn = getDBConnection();

// Initialize response array
$response = [
    'unread_count' => 0,
    'notifications' => []
];

try {
    $contactTableExists = false;
    $legacyTableExists = false;
    $t1 = $conn->query("SHOW TABLES LIKE 'contact_messages'");
    if ($t1 && $t1->num_rows > 0) {
        $contactTableExists = true;
    }
    $t2 = $conn->query("SHOW TABLES LIKE 'contactmessages'");
    if ($t2 && $t2->num_rows > 0) {
        $legacyTableExists = true;
    }

    $notifications = [];

    if ($contactTableExists) {
        $sql_messages = "SELECT id, name, email, subject, message, status, is_read, submitted_at,
                        CASE WHEN (COALESCE(is_read,0)=0 OR LOWER(TRIM(COALESCE(status,'')))='new') THEN 1 ELSE 0 END AS is_unread
                        FROM contact_messages
                        ORDER BY submitted_at DESC
                        LIMIT 10";
        $result_messages = $conn->query($sql_messages);
        if ($result_messages) {
            while ($row = $result_messages->fetch_assoc()) {
                $subject_text = !empty($row['subject']) ? $row['subject'] : substr((string) ($row['message'] ?? ''), 0, 80);
                $subject_text = strlen($subject_text) > 80 ? substr($subject_text, 0, 80) . '...' : $subject_text;
                $notifications[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => htmlspecialchars((string) $subject_text),
                    'message' => 'From ' . htmlspecialchars((string) ($row['name'] ?? 'Unknown')),
                    'time' => timeAgo((string) ($row['submitted_at'] ?? 'now')),
                    'read' => ((int) ($row['is_unread'] ?? 0)) !== 1,
                    'link' => 'ad_contactmessages.php',
                    'status' => (string) ($row['status'] ?? ''),
                    'email' => htmlspecialchars((string) ($row['email'] ?? '')),
                ];
            }
        }
        $countRes = $conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE (COALESCE(is_read,0)=0 OR LOWER(TRIM(COALESCE(status,'')))='new')");
        if ($countRes) {
            $cr = $countRes->fetch_assoc();
            $response['unread_count'] += (int) ($cr['c'] ?? 0);
        }
    }

    if ($legacyTableExists && count($notifications) < 10) {
        $need = 10 - count($notifications);
        $sql_legacy = "SELECT id, sender_name AS name, sender_email AS email, message, status, sent_at AS submitted_at
                       FROM contactmessages
                       ORDER BY sent_at DESC
                       LIMIT " . (int) $need;
        $legacyRes = $conn->query($sql_legacy);
        if ($legacyRes) {
            while ($row = $legacyRes->fetch_assoc()) {
                $msg = (string) ($row['message'] ?? '');
                $notifications[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => htmlspecialchars(strlen($msg) > 80 ? substr($msg, 0, 80) . '...' : $msg),
                    'message' => 'From ' . htmlspecialchars((string) ($row['name'] ?? 'Unknown')),
                    'time' => timeAgo((string) ($row['submitted_at'] ?? 'now')),
                    'read' => strtolower(trim((string) ($row['status'] ?? ''))) !== 'new',
                    'link' => 'ad_contactmessages.php',
                    'status' => (string) ($row['status'] ?? ''),
                    'email' => htmlspecialchars((string) ($row['email'] ?? '')),
                ];
            }
        }
        $legacyCountRes = $conn->query("SELECT COUNT(*) AS c FROM contactmessages WHERE LOWER(TRIM(COALESCE(status,'')))='new'");
        if ($legacyCountRes) {
            $lr = $legacyCountRes->fetch_assoc();
            $response['unread_count'] += (int) ($lr['c'] ?? 0);
        }
    }

    $response['notifications'] = $notifications;
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

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
