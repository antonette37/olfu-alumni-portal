<?php
/**
 * Register for an event (mirrors al_events.php register_event).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/includes/mobile_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth_id = mobile_auth_user_id();
if (!$auth_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$event_id = (int) ($input['event_id'] ?? 0);

if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    $conn = getDBConnection();

    $ev_stmt = $conn->prepare('SELECT id, title, event_date FROM events WHERE id = ? LIMIT 1');
    $ev_stmt->bind_param('i', $event_id);
    $ev_stmt->execute();
    $event = $ev_stmt->get_result()->fetch_assoc();
    $ev_stmt->close();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }

    $tableCheck = @$conn->query("SHOW TABLES LIKE 'event_registrations'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Event registration is not available']);
        exit;
    }

    $check = $conn->prepare('SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? LIMIT 1');
    $check->bind_param('ii', $event_id, $auth_id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        echo json_encode([
            'success' => true,
            'already_registered' => true,
            'message' => 'You are already registered for this event',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ins = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, status) VALUES (?, ?, 'Registered')");
    $ins->bind_param('ii', $event_id, $auth_id);
    if (!$ins->execute()) {
        throw new Exception('Insert failed');
    }
    $ins->close();

    echo json_encode([
        'success' => true,
        'message' => 'Registered successfully',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not register for event']);
}
