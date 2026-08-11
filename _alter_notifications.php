<?php
require_once __DIR__ . '/config/db.php';

$conn->query("DROP TABLE IF EXISTS notifications");

$sql = "CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT NULL,
    assignment_id INT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES blood_request(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES donor_assignments(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "notifications table altered successfully.\n";
} else {
    echo "Error altering notifications table: " . $conn->error . "\n";
}
?>
