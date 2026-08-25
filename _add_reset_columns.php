<?php
require_once __DIR__ . '/config/db.php';

// 1. Add reset columns to users table if they do not exist
$checkTokenCol = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($checkTokenCol && $checkTokenCol->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL DEFAULT NULL AFTER last_activity");
    echo "Added reset_token column to users table.\n";
} else {
    echo "reset_token column already exists.\n";
}

$checkExpiresCol = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_expires_at'");
if ($checkExpiresCol && $checkExpiresCol->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN reset_expires_at DATETIME NULL DEFAULT NULL AFTER reset_token");
    echo "Added reset_expires_at column to users table.\n";
} else {
    echo "reset_expires_at column already exists.\n";
}

// 2. Create email_logs table if it does not exist
$sqlEmailLogs = "CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NULL,
    user_id INT NULL,
    recipient_email VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(100) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    email_type VARCHAR(50) NOT NULL,
    status ENUM('Pending', 'Sent', 'Delivered', 'Failed', 'Bounced', 'Opened') DEFAULT 'Pending',
    error_message TEXT DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    opened_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sqlEmailLogs) === TRUE) {
    echo "email_logs table verified/created successfully.\n";
} else {
    echo "Error with email_logs table: " . $conn->error . "\n";
}
