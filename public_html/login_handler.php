<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
        exit();
    }

    $sql = "SELECT id, email, password, firstname, lastname, status FROM itcp WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if user is approved
        if ($user['status'] !== 'approved') {
            echo json_encode(['status' => 'error', 'message' => 'Your account is pending approval. Please wait for the approval email.']);
            exit();
        }

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['status'] = $user['status'];
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'firstname' => $user['firstname'],
                    'lastname' => $user['lastname']
                ]
            ]);
            exit();
        }
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
    exit();
}

$conn->close();
?> 