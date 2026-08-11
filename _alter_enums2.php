<?php
require_once __DIR__ . '/config/db.php';
$sql1 = "ALTER TABLE blood_request MODIFY status ENUM('Pending','Approved','Assigned','Completed','Rejected','Accepted','Received') DEFAULT 'Pending'";
if ($conn->query($sql1) === TRUE) {
    echo "blood_request table altered successfully for Assigned.\n";
} else {
    echo "Error altering blood_request table: " . $conn->error . "\n";
}
?>
