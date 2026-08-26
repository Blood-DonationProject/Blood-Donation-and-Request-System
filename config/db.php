<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname ="blood_donation";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Set charset for Myanmar/Unicode support
$conn->set_charset("utf8mb4");

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Automatically mark normal users inactive if no activity/login in 3 years (Admin accounts strictly excluded)
@$conn->query("
  UPDATE users 
  SET status = 'Inactive' 
  WHERE role = 'User' 
    AND status = 'Active' 
    AND (
      (last_activity IS NOT NULL AND last_activity <= DATE_SUB(NOW(), INTERVAL 3 YEAR))
      OR (last_activity IS NULL AND last_login IS NOT NULL AND last_login <= DATE_SUB(NOW(), INTERVAL 3 YEAR))
    )
");

// Ensure blood_request status enum includes 'Expired'
@$conn->query("ALTER TABLE blood_request MODIFY status ENUM('Pending','Approved','Assigned','Completed','Rejected','Accepted','Received','Cancelled','Expired') DEFAULT 'Pending'");

// Automatically mark uncompleted blood requests as Expired if required_date has passed (< CURDATE())
@$conn->query("
  UPDATE blood_request
  SET status = 'Expired'
  WHERE required_date < CURDATE()
    AND status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
");

// Restore donor availability and cancel assignments for expired requests
@$conn->query("
  UPDATE donor d
  JOIN donor_assignments da ON d.id = da.donor_id
  JOIN blood_request br ON da.request_id = br.id
  SET d.available_status = 'Available', da.status = 'Cancelled'
  WHERE br.status = 'Expired' AND da.status IN ('Assigned', 'Accepted')
");
?>