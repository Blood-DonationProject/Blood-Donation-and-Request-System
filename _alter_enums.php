<?php
require_once __DIR__ . '/config/db.php';

$sql1 = "ALTER TABLE blood_request MODIFY status ENUM('Pending','Approved','Completed','Rejected','Accepted','Received') DEFAULT 'Pending'";
if ($conn->query($sql1) === TRUE) {
    echo "blood_request table altered successfully.\n";
} else {
    echo "Error altering blood_request table: " . $conn->error . "\n";
}

$sql2 = "ALTER TABLE donor_assignments MODIFY status ENUM('Assigned', 'Accepted', 'Rejected', 'Received', 'Completed') DEFAULT 'Assigned'";
if ($conn->query($sql2) === TRUE) {
    echo "donor_assignments table altered successfully.\n";
} else {
    echo "Error altering donor_assignments table: " . $conn->error . "\n";
}
?>
