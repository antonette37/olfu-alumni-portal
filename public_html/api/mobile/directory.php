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

$user_id = mobile_auth_user_id();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = $_GET['search'] ?? '';

try {
    $conn = getDBConnection();
    
    if (!empty($search)) {
        $search_term = "%$search%";
        // Use correct column names
        $stmt = $conn->prepare('SELECT id, email, firstname, lastname, status, photo, personal_contact, address, year_graduated, program FROM itcp WHERE LOWER(status) IN (?, ?) AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?) ORDER BY lastname, firstname');
        $status1 = 'active';
        $status2 = 'approved';
        $stmt->bind_param('sssss', $status1, $status2, $search_term, $search_term, $search_term);
    } else {
        // Use correct column names
        $stmt = $conn->prepare('SELECT id, email, firstname, lastname, status, photo, personal_contact, address, year_graduated, program FROM itcp WHERE LOWER(status) IN (?, ?) ORDER BY lastname, firstname LIMIT 100');
        $status1 = 'active';
        $status2 = 'approved';
        $stmt->bind_param('ss', $status1, $status2);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        // Map database columns to mobile app expected field names
        $users[] = [
            'id' => (int)$row['id'],
            'email' => $row['email'],
            'firstname' => $row['firstname'] ?? '',
            'lastname' => $row['lastname'] ?? '',
            'status' => $row['status'] ?? 'active',
            'profile_image' => mobile_resolve_profile_image_url($row['photo'] ?? null, 'https://ccsolfualumni.sbs', (int) $row['id']),
            'photo' => $row['photo'] ?? null,
            'phone' => $row['personal_contact'] ?? null,
            'address' => $row['address'] ?? null,
            'batch' => $row['year_graduated'] ?? null,
            'course' => $row['program'] ?? null
        ];
    }
    $stmt->close();
    
    echo json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

