<?php
/**
 * Apply to a job posting (mirrors apply_job_message.php for mobile).
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
$job_id = (int) ($input['job_id'] ?? 0);
$custom_message = trim($input['message'] ?? '');

if ($job_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Job ID is required']);
    exit;
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare(
        "SELECT j.id, j.title, j.user_id, j.status
         FROM jobs j
         WHERE j.id = ? AND j.status = 'active'
         LIMIT 1"
    );
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job not found or no longer available']);
        exit;
    }

    $receiver_id = (int) $job['user_id'];
    if ($receiver_id === $auth_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You cannot apply to your own job posting']);
        exit;
    }

    $already = false;
    foreach (['applicant_id', 'alumni_id'] as $col) {
        $chk = @$conn->prepare("SELECT id FROM job_applications WHERE job_id = ? AND {$col} = ? LIMIT 1");
        if (!$chk) {
            continue;
        }
        $chk->bind_param('ii', $job_id, $auth_id);
        if ($chk->execute()) {
            $r = $chk->get_result();
            if ($r && $r->num_rows > 0) {
                $already = true;
            }
        }
        $chk->close();
        if ($already) {
            break;
        }
    }

    if ($already) {
        echo json_encode([
            'success' => true,
            'already_applied' => true,
            'message' => 'You have already applied for this job',
            'receiver_id' => $receiver_id,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $job_title = $job['title'] ?: 'this job posting';
    $message_body = $custom_message !== ''
        ? $custom_message
        : 'Hi! I am interested in the "' . $job_title . '" position. How can I apply?';

    $conversation_id = null;
    $conv_stmt = $conn->prepare(
        'SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?) LIMIT 1'
    );
    if ($conv_stmt) {
        $conv_stmt->bind_param('iiii', $auth_id, $receiver_id, $receiver_id, $auth_id);
        $conv_stmt->execute();
        $conv_row = $conv_stmt->get_result()->fetch_assoc();
        $conv_stmt->close();
        if ($conv_row) {
            $conversation_id = (int) $conv_row['id'];
        }
    }

    if (!$conversation_id) {
        $create_conv = $conn->prepare('INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)');
        if ($create_conv) {
            $create_conv->bind_param('ii', $auth_id, $receiver_id);
            $create_conv->execute();
            $conversation_id = (int) $conn->insert_id;
            $create_conv->close();
        }
    }

    if ($conversation_id) {
        $msg_stmt = $conn->prepare(
            'INSERT INTO messages (conversation_id, sender_id, receiver_id, message, attachment) VALUES (?, ?, ?, ?, ?)'
        );
        if ($msg_stmt) {
            $attachment = null;
            $msg_stmt->bind_param('iiiss', $conversation_id, $auth_id, $receiver_id, $message_body, $attachment);
            $msg_stmt->execute();
            $msg_stmt->close();
        }
    }

    @$conn->query("CREATE TABLE IF NOT EXISTS job_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        applicant_id INT NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_application (job_id, applicant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ins = @$conn->prepare(
        'INSERT INTO job_applications (job_id, applicant_id, applied_at) VALUES (?, ?, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE applied_at = applied_at'
    );
    if ($ins) {
        $ins->bind_param('ii', $job_id, $auth_id);
        $ins->execute();
        $ins->close();
    } else {
        $ins2 = @$conn->prepare(
            'INSERT INTO job_applications (job_id, alumni_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE job_id = job_id'
        );
        if ($ins2) {
            $ins2->bind_param('ii', $job_id, $auth_id);
            @$ins2->execute();
            $ins2->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Application sent successfully',
        'receiver_id' => $receiver_id,
        'conversation_id' => $conversation_id,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not submit application']);
}
