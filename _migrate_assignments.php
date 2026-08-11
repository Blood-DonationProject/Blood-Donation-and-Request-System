<?php
require_once __DIR__ . '/config/db.php';

// 1. Create donor_assignments table
$sql_assignments = "
CREATE TABLE IF NOT EXISTS donor_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    donor_id INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Declined', 'Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES blood_request(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES donor(id) ON DELETE CASCADE
);
";

if ($conn->query($sql_assignments) === TRUE) {
    echo "donor_assignments table created successfully.\n";
} else {
    echo "Error creating donor_assignments table: " . $conn->error . "\n";
}

// 2. Create notifications table
$sql_notifications = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
";

if ($conn->query($sql_notifications) === TRUE) {
    echo "notifications table created successfully.\n";
} else {
    echo "Error creating notifications table: " . $conn->error . "\n";
}
?>
