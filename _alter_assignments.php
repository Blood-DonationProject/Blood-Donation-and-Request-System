<?php
require_once __DIR__ . '/config/db.php';

$conn->query("DROP TABLE IF EXISTS donor_assignments");

$sql = "CREATE TABLE donor_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    donor_id INT NOT NULL,
    assigned_by INT NOT NULL,
    status ENUM('Assigned', 'Accepted', 'Rejected', 'Completed') DEFAULT 'Assigned',
    responded_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES blood_request(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES donor(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "donor_assignments table altered successfully.\n";
} else {
    echo "Error altering donor_assignments table: " . $conn->error . "\n";
}
?>
