<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/db.php';
$sql = "ALTER TABLE users ADD COLUMN last_login DATETIME NULL";
if ($conn->query($sql) === TRUE) {
    echo "Column last_login added successfully\n";
} else {
    echo "Error adding column: " . $conn->error . "\n";
}
$sql2 = "DESCRIBE users";
$res = $conn->query($sql2);
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
$conn->close();
?>
