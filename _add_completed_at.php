<?php
require_once __DIR__ . '/config/db.php';

$result = $conn->query("SHOW COLUMNS FROM donor_assignments LIKE 'completed_at'");
if ($result && $result->num_rows == 0) {
    $sql = "ALTER TABLE donor_assignments ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "Column completed_at added successfully.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column completed_at already exists.";
}
?>
