<?php
require_once 'config/db.php';

// Add assigned_donor_id column to blood_request
$conn->query("ALTER TABLE blood_request ADD COLUMN assigned_donor_id INT DEFAULT NULL AFTER requester_name");

// Add foreign key constraint
$conn->query("ALTER TABLE blood_request ADD CONSTRAINT fk_assigned_donor FOREIGN KEY (assigned_donor_id) REFERENCES donor(id) ON DELETE SET NULL");

echo "assigned_donor_id column added successfully!" . PHP_EOL;

// Verify
$result = $conn->query('DESCRIBE blood_request');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . ($row['Null'] ?? '') . PHP_EOL;
    }
}

$conn->close();
