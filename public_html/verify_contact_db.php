<?php
// Database verification script for contact form
require_once 'db_config.php';

echo "<h2>Contact Form Database Verification</h2>";

$conn = getDBConnection();

if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connection successful</p>";

// Check if contact_messages table exists
$result = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✅ contact_messages table exists</p>";
    
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $result = $conn->query("DESCRIBE contact_messages");
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show recent records
    echo "<h3>Recent Contact Messages:</h3>";
    $result = $conn->query("SELECT * FROM contact_messages ORDER BY submitted_at DESC LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Read</th><th>Date</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "<td>" . ($row['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . $row['submitted_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No contact messages found.</p>";
    }
    
} else {
    echo "<p style='color: orange;'>⚠️ contact_messages table does not exist</p>";
    echo "<p>This will be created automatically when the contact form is submitted.</p>";
}

// Test prepared statement
echo "<h3>Testing Prepared Statement:</h3>";
$stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message, status, is_read) VALUES (?, ?, ?, 'New', 0)");
if ($stmt) {
    echo "<p style='color: green;'>✅ Prepared statement works correctly</p>";
    $stmt->close();
} else {
    echo "<p style='color: red;'>❌ Prepared statement failed: " . $conn->error . "</p>";
}

$conn->close();
?>
