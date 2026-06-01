<?php
require_once 'db_config.php';
$conn = getDBConnection();

// Get user ID from GET request
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id > 0) {
    // Fetch user data
    $sql = "SELECT id, firstname, lastname, email, personal_contact, address, program, campus, date_joined, photo, year_graduated, resume FROM itcp WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        echo json_encode($user_data);
    } else {
        echo json_encode(["error" => "User not found"]);
    }

    $stmt->close();
} else {
    echo json_encode(["error" => "Invalid user ID"]);
}

$conn->close();
?> 