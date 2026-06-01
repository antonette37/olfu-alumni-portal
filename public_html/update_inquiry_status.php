<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: al_login.php');
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$qs = '?search=' . urlencode($_POST['search'] ?? '')
    . '&status=' . urlencode($_POST['status'] ?? '')
    . '&sort=' . urlencode($_POST['sort'] ?? '')
    . '&order=' . urlencode($_POST['order'] ?? '')
    . '&page=' . urlencode($_POST['page'] ?? '1');
$allowed = ['New','In Progress','Resolved','Spam'];
if ($id <= 0 || !in_array($status, $allowed, true)) {
    header('Location: ad_contactmessages.php' . $qs);
    exit();
}

@$conn->query("ALTER TABLE contact_messages ADD COLUMN status ENUM('New','In Progress','Resolved','Spam') NOT NULL DEFAULT 'New'");
$stmt = $conn->prepare('UPDATE contact_messages SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $id);
$stmt->execute();
$stmt->close();

header('Location: ad_contactmessages.php' . $qs);
exit();

