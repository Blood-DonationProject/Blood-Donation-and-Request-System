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

try {
  // Automatically mark normal users inactive if no activity/login in 3 years (Admin accounts strictly excluded)
  $conn->query("
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
  $conn->query("ALTER TABLE blood_request MODIFY status ENUM('Pending','Approved','Assigned','Completed','Rejected','Accepted','Received','Cancelled','Expired') DEFAULT 'Pending'");

  // Ensure users table has phone and address columns for centralized profile contact info
  $chkPhone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
  if ($chkPhone && $chkPhone->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(15) DEFAULT NULL");
  }
  $chkAddr = $conn->query("SHOW COLUMNS FROM users LIKE 'address'");
  if ($chkAddr && $chkAddr->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL");
  }

  // Automatically mark uncompleted blood requests as Expired if required_date has passed (< CURDATE())
  $conn->query("
    UPDATE blood_request
    SET status = 'Expired'
    WHERE required_date < CURDATE()
      AND status NOT IN ('Completed', 'Rejected', 'Cancelled', 'Expired')
  ");

  // Restore donor availability and cancel assignments for expired requests
  $conn->query("
    UPDATE donor d
    JOIN donor_assignments da ON d.id = da.donor_id
    JOIN blood_request br ON da.request_id = br.id
    SET d.available_status = 'Available', da.status = 'Cancelled'
    WHERE br.status = 'Expired' AND da.status IN ('Assigned', 'Accepted')
  ");

  // Automatically restore donor availability if 3 calendar months have passed since last donation date
  $conn->query("
    UPDATE donor
    SET available_status = 'Available'
    WHERE last_donation_date IS NOT NULL
      AND last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
      AND available_status = 'Unavailable'
  ");
  // Ensure notifications table has donor_id and related_user_id columns
  $chkDonorCol = $conn->query("SHOW COLUMNS FROM notifications LIKE 'donor_id'");
  if ($chkDonorCol && $chkDonorCol->num_rows === 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN donor_id INT NULL DEFAULT NULL AFTER assignment_id");
  }
  $chkUserCol = $conn->query("SHOW COLUMNS FROM notifications LIKE 'related_user_id'");
  if ($chkUserCol && $chkUserCol->num_rows === 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN related_user_id INT NULL DEFAULT NULL AFTER donor_id");
  }

  // Ensure email_logs table exists and has related_id
  $conn->query("CREATE TABLE IF NOT EXISTS `email_logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `notification_id` INT NULL,
      `user_id` INT NULL,
      `related_id` INT NULL,
      `recipient_email` VARCHAR(100) NOT NULL,
      `recipient_name` VARCHAR(100) DEFAULT NULL,
      `subject` VARCHAR(255) NOT NULL,
      `email_type` VARCHAR(50) NOT NULL,
      `status` ENUM('Pending','Sent','Delivered','Failed','Bounced','Opened') DEFAULT 'Pending',
      `error_message` TEXT DEFAULT NULL,
      `sent_at` TIMESTAMP NULL DEFAULT NULL,
      `delivered_at` TIMESTAMP NULL DEFAULT NULL,
      `opened_at` TIMESTAMP NULL DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $chkRelCol = $conn->query("SHOW COLUMNS FROM email_logs LIKE 'related_id'");
  if ($chkRelCol && $chkRelCol->num_rows === 0) {
    $conn->query("ALTER TABLE email_logs ADD COLUMN related_id INT NULL DEFAULT NULL AFTER user_id");
  }

  // Reassign any legacy notifications where user_id = 0 to first active admin
  $adminRes = $conn->query("SELECT id FROM users WHERE role = 'Admin' AND (status = 'Active' OR status IS NULL) LIMIT 1");
  if ($adminRes && $adminRow = $adminRes->fetch_assoc()) {
    $adminId = (int)$adminRow['id'];
    $conn->query("UPDATE notifications SET user_id = {$adminId} WHERE user_id = 0");
  }
} catch (\Throwable $e) {
  // Silent failover to prevent unhandled database exceptions during initialization
}
?>