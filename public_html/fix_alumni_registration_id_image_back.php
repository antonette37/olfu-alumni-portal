<?php
/**
 * One-time fix: add id_image_back column to alumni_registration if missing.
 * Open in browser once (e.g. fix_alumni_registration_id_image_back.php).
 * Delete or restrict after use.
 */
session_start();
require_once __DIR__ . '/db_config.php';

header('Content-Type: text/html; charset=utf-8');

$conn = getDBConnection();

// Check if column already exists
$check = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumni_registration' AND COLUMN_NAME = 'id_image_back'");
$exists = false;
if ($check && $check->num_rows > 0) {
    $row = $check->fetch_assoc();
    $exists = isset($row['c']) && (int)$row['c'] > 0;
}
if ($check) $check->close();

if ($exists) {
    echo '<p><strong>Column id_image_back already exists.</strong> No change needed.</p>';
    echo '<p><a href="check_alumni_registration_id_back.php">Check database again</a></p>';
    $conn->close();
    exit;
}

// Add the column
$ok = $conn->query("ALTER TABLE alumni_registration ADD COLUMN id_image_back VARCHAR(255) NULL AFTER id_image");

if ($ok) {
    echo '<p><strong>Success:</strong> Column <code>id_image_back</code> was added to <code>alumni_registration</code>.</p>';
    echo '<p>New registrations will now store the back-of-ID image, and the admin Review modal will show it when available.</p>';
    echo '<p><a href="check_alumni_registration_id_back.php">Verify (check database)</a> &nbsp; <a href="ad_user_management.php">User Management</a></p>';
} else {
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($conn->error) . '</p>';
    echo '<p>You can run this SQL manually in phpMyAdmin or your MySQL client:</p>';
    echo '<pre>ALTER TABLE alumni_registration ADD COLUMN id_image_back VARCHAR(255) NULL AFTER id_image;</pre>';
}

$conn->close();
