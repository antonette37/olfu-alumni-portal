<?php
require_once 'db_config.php';
$conn = getDBConnection();

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "Select user to edit.";
    exit;
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $conn->prepare("DELETE FROM itcp WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {

        header("Location: ad_alumnirecord.php");
        exit();
    } else {
        echo "Error deleting record: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}

$conn->close();
?>
