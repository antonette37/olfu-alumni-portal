<?php
/**
 * Career Center — active job listings (mirrors al_career.php job board).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/includes/mobile_auth.php';

$compat = __DIR__ . '/../../includes/mysqli_compat.php';
if (is_file($compat)) {
    require_once $compat;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    $conn = getDBConnection();
    $jobs = [];

    $sql = "SELECT j.id, j.title, j.company, j.location, j.job_type, j.salary_range, j.description,
                   j.requirements, j.posted_date, j.user_id,
                   u.firstname AS poster_firstname, u.lastname AS poster_lastname
            FROM jobs j
            LEFT JOIN itcp u ON j.user_id = u.id
            WHERE j.status = 'active'
            ORDER BY j.posted_date DESC
            LIMIT 80";

    $res = @$conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['has_applied'] = 0;
            $jobs[] = $row;
        }
    }

    $applied = [];
    foreach (['applicant_id', 'alumni_id'] as $col) {
        $appStmt = @$conn->prepare("SELECT job_id FROM job_applications WHERE {$col} = ?");
        if (!$appStmt) {
            continue;
        }
        $appStmt->bind_param('i', $auth_id);
        if ($appStmt->execute()) {
            $ar = $appStmt->get_result();
            if ($ar) {
                while ($row = $ar->fetch_assoc()) {
                    $applied[(int) $row['job_id']] = true;
                }
            }
        }
        $appStmt->close();
    }

    foreach ($jobs as &$j) {
        $jid = (int) ($j['id'] ?? 0);
        $j['has_applied'] = isset($applied[$jid]) ? 1 : 0;
        $j['id'] = $jid;
    }
    unset($j);

    echo json_encode(['success' => true, 'jobs' => $jobs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
