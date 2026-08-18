<?php
require_once __DIR__ . '/../config/db.php';

// Alter blood_request
$sql1 = "ALTER TABLE blood_request MODIFY status ENUM('Pending','Approved','Assigned','Completed','Rejected','Accepted','Received','Cancelled') DEFAULT 'Pending'";
if ($conn->query($sql1) === TRUE) {
    echo "blood_request table altered successfully.\n";
} else {
    echo "Error altering blood_request table: " . $conn->error . "\n";
}

// Alter donor_assignments
$sql2 = "ALTER TABLE donor_assignments MODIFY status ENUM('Assigned','Accepted','Rejected','Received','Completed','Cancelled') DEFAULT 'Assigned'";
if ($conn->query($sql2) === TRUE) {
    echo "donor_assignments table altered successfully.\n";
} else {
    echo "Error altering donor_assignments table: " . $conn->error . "\n";
}
?>
