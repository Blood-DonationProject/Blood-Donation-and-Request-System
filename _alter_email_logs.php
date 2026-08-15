<?php
require_once __DIR__ . '/config/db.php';

$queries = [
    "ALTER TABLE email_logs MODIFY COLUMN status ENUM('Pending', 'Sent', 'Delivered', 'Failed', 'Bounced', 'Opened') DEFAULT 'Pending';",
    "ALTER TABLE email_logs ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER sent_at;",
    "ALTER TABLE email_logs ADD COLUMN opened_at TIMESTAMP NULL DEFAULT NULL AFTER delivered_at;"
];

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Successfully executed: $sql\n";
    } else {
        echo "Error executing $sql: " . $conn->error . "\n";
    }
}
$conn->close();
echo "Migration complete.\n";
?>
