<?php
require_once 'config/db.php';
$result = $conn->query('DESCRIBE blood_request');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . ($row['Null'] ?? '') . PHP_EOL;
    }
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
