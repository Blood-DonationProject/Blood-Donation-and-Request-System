<?php
require_once __DIR__ . '/config/db.php';
$sql = "ALTER TABLE email_logs ADD COLUMN error_message TEXT NULL";
if ($conn->query($sql) === TRUE) {
    file_put_contents(__DIR__ . '/db_out.txt', "Success");
} else {
    file_put_contents(__DIR__ . '/db_out.txt', "Error: " . $conn->error);
}
$conn->close();
?>
