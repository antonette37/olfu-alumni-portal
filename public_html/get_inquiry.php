<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['success' => false]); exit(); }

@$conn->query("ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
$stmt = $conn->prepare('SELECT id, name, email, subject, message, status, is_read, submitted_at FROM contact_messages WHERE id = ?');
if (!$stmt) {
    echo json_encode(['success' => false]);
    exit();
}

$stmt->bind_param('i', $id);
$stmt->execute();

// Try get_result when available; otherwise, fall back to bind_result/fetch
$row = null;
if (method_exists($stmt, 'get_result')) {
    $res = $stmt->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
    }
} 

if (!$row) {
    $stmt->store_result();
    $stmt->bind_result($rid, $rname, $remail, $rsubject, $rmessage, $rstatus, $ris_read, $rsubmitted_at);
    if ($stmt->fetch()) {
        $row = [
            'id' => $rid,
            'name' => $rname,
            'email' => $remail,
            'subject' => $rsubject,
            'message' => $rmessage,
            'status' => $rstatus,
            'is_read' => $ris_read,
            'submitted_at' => $rsubmitted_at,
        ];
    }
}

$stmt->close();

if (!$row) { echo json_encode(['success' => false]); exit(); }
echo json_encode(['success' => true, 'inquiry' => $row]);

