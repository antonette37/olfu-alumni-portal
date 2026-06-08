<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 3600');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../db_config.php';
require_once __DIR__ . '/includes/resolve_profile_image.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed. Expected POST, got: ' . $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

try {
    $conn = getDBConnection();

    // Check for admin credentials
    $admin_username = "admin";
    $admin_password = getenv('ADMIN_PASSWORD') ?: ''; // Must be set in environment

    if ($email === $admin_username && !empty($admin_password) && $password === $admin_password) {
        // Admin login - return admin user data
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => 0,
                'email' => 'admin',
                'firstname' => 'Admin',
                'lastname' => 'User',
                'status' => 'active'
            ],
            'token' => 'admin_token'
        ]);
        exit;
    }
    
    // Check for regular user
    $email_lower = strtolower($email);
    // Use correct column names: photo (not profile_image), personal_contact (not phone), 
    // year_graduated (not batch), program (not course)
    $stmt = $conn->prepare('SELECT id, email, password, firstname, lastname, status, photo, personal_contact, address, year_graduated, program FROM itcp WHERE LOWER(email) = ?');
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $email_lower);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
    
    if (count($users) === 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    $user = null;
    $passwordMatch = false;
    
    foreach ($users as $candidate) {
        if (!empty($candidate['password']) && password_verify($password, $candidate['password'])) {
            $user = $candidate;
            $passwordMatch = true;
            break;
        }
    }
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    // Check if status is 'active' or 'approved' (case-insensitive)
    // Also handle 'Active' (capital A) which is common in database
    $rawStatus = trim($user['status'] ?? '');
    $userStatus = strtolower($rawStatus);
    $isActive = ($userStatus === 'active' || $userStatus === 'approved');
    
    if ($isActive) {
        // Remove password from response
        unset($user['password']);
        
        // Ensure all fields are properly formatted for mobile app
        // Map database columns to mobile app expected field names
        $userResponse = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'firstname' => $user['firstname'] ?? '',
            'lastname' => $user['lastname'] ?? '',
            'status' => $user['status'] ?? 'active',
            'profile_image' => mobile_resolve_profile_image_url($user['photo'] ?? null, 'https://ccsolfualumni.sbs', (int) $user['id']),
            'profile_image_data' => mobile_resolve_profile_image_data($user['photo'] ?? null, (int) $user['id']),
            'photo' => $user['photo'] ?? null,
            'phone' => $user['personal_contact'] ?? null, // Map personal_contact to phone
            'address' => $user['address'] ?? null,
            'batch' => $user['year_graduated'] ?? null, // Map year_graduated to batch
            'course' => $user['program'] ?? null // Map program to course
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => $userResponse,
            'token' => 'token_' . $user['id'] // Simple token, you can implement JWT later
        ]);
    } else {
        http_response_code(401);
        $statusMessage = $user['status'] ?? 'unknown';
        echo json_encode([
            'success' => false,
            'message' => 'Your account is not active. Current status: ' . $statusMessage . '. Please contact support if you believe this is an error.'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    if (isset($conn)) {
        $conn->close();
    }
}
?>

