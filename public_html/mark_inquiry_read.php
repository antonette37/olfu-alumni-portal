<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: al_login.php');
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$qs = '?search=' . urlencode($_POST['search'] ?? '')
    . '&status=' . urlencode($_POST['status'] ?? '')
    . '&sort=' . urlencode($_POST['sort'] ?? '')
    . '&order=' . urlencode($_POST['order'] ?? '')
    . '&page=' . urlencode($_POST['page'] ?? '1');
if ($id <= 0) { header('Location: ad_contactmessages.php' . $qs); exit(); }

@$conn->query("ALTER TABLE contact_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
$stmt = $conn->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

header('Location: ad_contactmessages.php' . $qs);
exit();

