<?php
require_once 'config/db.php';

// Add requester_name column
$conn->query("ALTER TABLE blood_request ADD COLUMN requester_name VARCHAR(100) DEFAULT NULL AFTER users_id");

// Update existing records with usernames
$conn->query("
    UPDATE blood_request br
    INNER JOIN users u ON br.users_id = u.id
    SET br.requester_name = u.username
    WHERE br.requester_name IS NULL OR br.requester_name = ''
");

echo "Column added and records updated successfully!" . PHP_EOL;

// Verify
$result = $conn->query('DESCRIBE blood_request');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' | ' . $row['Type'] . PHP_EOL;
    }
}

$conn->close();
