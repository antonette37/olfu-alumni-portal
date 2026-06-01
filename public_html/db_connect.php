<?php
require_once 'db_config.php';
$conn = getDBConnection();

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Log error and show generic message
    error_log("Database connection error: " . $e->getMessage());
    die("A database connection error occurred.");
}
?>
