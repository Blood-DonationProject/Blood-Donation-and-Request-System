<?php
require_once __DIR__ . '/../config/db.php';
$sql = "ALTER TABLE users ADD COLUMN last_activity DATETIME NULL";
if ($conn->query($sql) === TRUE) {
    echo "Column last_activity added successfully\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}
$conn->close();
?>
