<?php
require_once __DIR__ . '/config/db.php';
$sql = "ALTER TABLE blood_request ADD COLUMN received_at TIMESTAMP NULL DEFAULT NULL AFTER required_date";
if ($conn->query($sql)) {
    echo "Added received_at successfully.";
} else {
    echo "Error: " . $conn->error;
}
?>
