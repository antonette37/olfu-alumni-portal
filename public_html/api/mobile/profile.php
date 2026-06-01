<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

require_once '../../db_config.php';
require_once __DIR__ . '/includes/resolve_profile_image.php';
require_once __DIR__ . '/includes/mobile_auth.php';

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

$view_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$user_id = $view_id > 0 ? $view_id : $auth_id;

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare(
        "SELECT id, email, firstname, lastname, status, photo, personal_contact, address, year_graduated, program, employment_status, company, position
         FROM itcp WHERE id = ? AND LOWER(status) IN ('active', 'approved') LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Map database columns to mobile app expected field names
    $userResponse = [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'firstname' => $user['firstname'] ?? '',
        'lastname' => $user['lastname'] ?? '',
        'status' => $user['status'] ?? 'active',
        'profile_image' => mobile_resolve_profile_image_url($user['photo'] ?? null, 'https://ccsolfualumni.sbs', $user_id),
        'photo' => $user['photo'] ?? null,
        'phone' => $user['personal_contact'] ?? null,
        'address' => $user['address'] ?? null,
        'batch' => $user['year_graduated'] ?? null,
        'course' => $user['program'] ?? null,
        'employment_status' => $user['employment_status'] ?? null,
        'company' => $user['company'] ?? null,
        'position' => $user['position'] ?? null
    ];
    
    echo json_encode($userResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

