<?php
require_once 'config.php';

function createNotification($conn, $user_id, $type, $title, $message, $reference_id = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, reference_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issiis", $user_id, $type, $title, $message, $reference_id);
    return $stmt->execute();
}

// Example usage:
// createNotification($conn, $user_id, 'new_registration', 'New user registration: ' . $user_name, $user_id);
?> 