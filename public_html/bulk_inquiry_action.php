<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: al_login.php');
    exit();
}

$action = $_POST['bulk_action'] ?? '';
$qs = '?search=' . urlencode($_POST['search'] ?? '')
    . '&status=' . urlencode($_POST['status'] ?? '')
    . '&sort=' . urlencode($_POST['sort'] ?? '')
    . '&order=' . urlencode($_POST['order'] ?? '')
    . '&page=' . urlencode($_POST['page'] ?? '1');
$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
if (!$action || empty($ids)) { header('Location: ad_contactmessages.php' . $qs); exit(); }

$in = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

if ($action === 'mark_read') {
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read = 1 WHERE id IN ($in)");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $stmt->close();
} elseif (strpos($action, 'set_status_') === 0) {
    $status = substr($action, strlen('set_status_'));
    $allowed = ['New','In Progress','Resolved','Spam'];
    if (!in_array($status, $allowed, true)) { header('Location: ad_contactmessages.php'); exit(); }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE id IN ($place)");
    $stmt->bind_param('s' . $types, $status, ...$ids);
    $stmt->execute();
    $stmt->close();
}

header('Location: ad_contactmessages.php' . $qs);
exit();

