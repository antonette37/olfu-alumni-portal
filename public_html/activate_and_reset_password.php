<?php
// Usage:
// - POST: email=you@example.com&new_password=YourNewPass
// - or GET:  ?email=you@example.com&new_password=YourNewPass
header('Content-Type: application/json');
require_once 'db_config.php';

$methodData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$email = strtolower(trim($methodData['email'] ?? ''));
$newPlain = trim($methodData['new_password'] ?? 'Test@1234');
if ($email === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing email']);
    exit;
}

try {
    $conn = getDBConnection();
    $hash = password_hash($newPlain, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE itcp SET status='active', password=? WHERE LOWER(email)=? LIMIT 1");
    if (!$stmt) { throw new Exception('Prepare failed: ' . $conn->error); }
    $stmt->bind_param('ss', $hash, $email);
    if (!$stmt->execute()) { throw new Exception('Execute failed: ' . $stmt->error); }

    $changed = $stmt->affected_rows;
    $stmt->close();
    $conn->close();

    echo json_encode(['ok' => true, 'updated' => $changed, 'login_password' => $newPlain]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>


