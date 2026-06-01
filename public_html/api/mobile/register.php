<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$firstname = trim($input['firstname'] ?? '');
$lastname = trim($input['lastname'] ?? '');
$phone = trim($input['phone'] ?? '');
$batch = trim($input['batch'] ?? '');
$course = trim($input['course'] ?? '');

if (empty($email) || empty($password) || empty($firstname) || empty($lastname)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email, password, first name, and last name are required']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Check if email already exists
    $check_stmt = $conn->prepare('SELECT id FROM itcp WHERE LOWER(email) = ?');
    $email_lower = strtolower($email);
    $check_stmt->bind_param('s', $email_lower);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user - use correct column names
    // Map: phone -> personal_contact, batch -> year_graduated, course -> program
    $stmt = $conn->prepare('INSERT INTO itcp (email, password, firstname, lastname, personal_contact, year_graduated, program, status, consent, photo, middlename, name_ext, birthday, age, gender, civil_status, religion, nationality, address, emergency_contact, student_number, campus, month_graduated, post_grad, licensure_exam, club_involvement, employment_status, company, industry, position, employment_history, previous_role, length_of_service) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $status = 'pending';
    $consent = 1;
    
    // Set default values for required fields
    $photo = '';
    $middlename = '';
    $name_ext = '';
    $birthday = '2000-01-01';
    $age = 0;
    $gender = '';
    $civil_status = 'Single';
    $religion = '';
    $nationality = 'Filipino';
    $address = '';
    $emergency_contact = '';
    $student_number = '';
    $campus = '';
    $month_graduated = '';
    $post_grad = '';
    $licensure_exam = '';
    $club_involvement = '';
    $employment_status = '';
    $company = '';
    $industry = '';
    $position = '';
    $employment_history = '';
    $previous_role = '';
    $length_of_service = '';
    
    // Parameter types: 8 strings, 1 int, 1 string, 3 strings, 1 int, 22 strings = 33 params (31s + 2i)
    $stmt->bind_param('ssssssssissssissssssssssssssssssss', 
        $email, $hashed_password, $firstname, $lastname, 
        $phone, $batch, $course, $status, $consent,
        $photo, $middlename, $name_ext, $birthday, $age, 
        $gender, $civil_status, $religion, $nationality, 
        $address, $emergency_contact, $student_number, 
        $campus, $month_graduated, $post_grad, $licensure_exam, 
        $club_involvement, $employment_status, $company, 
        $industry, $position, $employment_history, $previous_role, 
        $length_of_service
    );
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Fetch created user - use correct column names
        $user_stmt = $conn->prepare('SELECT id, email, firstname, lastname, status, photo, personal_contact, address, year_graduated, program FROM itcp WHERE id = ?');
        $user_stmt->bind_param('i', $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        $user_stmt->close();
        
        // Map database columns to mobile app expected field names
        $userResponse = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'firstname' => $user['firstname'] ?? '',
            'lastname' => $user['lastname'] ?? '',
            'status' => $user['status'] ?? 'pending',
            'profile_image' => $user['photo'] ?? null,
            'phone' => $user['personal_contact'] ?? null,
            'address' => $user['address'] ?? null,
            'batch' => $user['year_graduated'] ?? null,
            'course' => $user['program'] ?? null
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful. Your account is pending approval.',
            'user' => $userResponse
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>

