-- Blood Donation and Request Management System Schema
-- Generates all required tables, relationships, and constraints

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100),
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin','User') NOT NULL DEFAULT 'User',
    `status` ENUM('Active','Inactive') DEFAULT 'Active',
    `myanmar_name` VARCHAR(100) DEFAULT NULL,
    `last_activity` TIMESTAMP NULL DEFAULT NULL,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `reset_token` VARCHAR(255) DEFAULT NULL,
    `reset_expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Table structure for `blood_groups`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `blood_gp_name` VARCHAR(5) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial data for `blood_groups`
INSERT IGNORE INTO `blood_groups` (`blood_gp_name`) VALUES
('A+'), ('A-'), ('B+'), ('B-'), ('AB+'), ('AB-'), ('O+'), ('O-');

-- Initial admin user for `users`
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`) VALUES
('admin', 'bloodcommunicationsystem12@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin'); -- Password is 'password'

-- --------------------------------------------------------
-- 3. Table structure for `donor`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donor` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `gender` ENUM('Male','Female','Other') NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `age` INT NOT NULL,
    `blood_groups` VARCHAR(5) NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `address` TEXT NOT NULL,
    `weight` DECIMAL(5,2) NOT NULL,
    `last_donation_date` DATE DEFAULT NULL,
    `available_status` ENUM('Available','Unavailable') DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Table structure for `blood_request`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_request` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `users_id` INT NOT NULL,
    `requester_name` VARCHAR(100) DEFAULT NULL,
    `blood_groups_id` INT NOT NULL,
    `units` INT NOT NULL,
    `hospital` VARCHAR(100) NOT NULL,
    `required_date` DATE NOT NULL,
    `received_at` TIMESTAMP NULL DEFAULT NULL,
    `status` ENUM('Pending','Approved','Assigned','Completed','Rejected','Accepted','Received') DEFAULT 'Pending',
    `urgency` ENUM('Normal','Urgent') DEFAULT 'Normal',
    `assigned_donor_id` INT DEFAULT NULL,
    FOREIGN KEY (`users_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`blood_groups_id`) REFERENCES `blood_groups`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`assigned_donor_id`) REFERENCES `donor`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Table structure for `donor_assignments`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donor_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `donor_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `status` ENUM('Assigned','Accepted','Rejected','Received','Completed') DEFAULT 'Assigned',
    `responded_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`request_id`) REFERENCES `blood_request`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`donor_id`) REFERENCES `donor`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Table structure for `donation_history`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donation_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `donor_id` INT NOT NULL,
    `users_id` INT NOT NULL,
    `request_id` INT NOT NULL,
    `blood_groups_id` INT NOT NULL,
    `units` INT NOT NULL,
    `donation_date` DATE NOT NULL,
    `status` ENUM('Completed') DEFAULT 'Completed',
    FOREIGN KEY (`donor_id`) REFERENCES `donor`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`users_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`request_id`) REFERENCES `blood_request`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`blood_groups_id`) REFERENCES `blood_groups`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Table structure for `notifications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `request_id` INT NULL,
    `assignment_id` INT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`request_id`) REFERENCES `blood_request`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assignment_id`) REFERENCES `donor_assignments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Table structure for `email_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notification_id` INT NULL,
    `user_id` INT NULL,
    `recipient_email` VARCHAR(100) NOT NULL,
    `recipient_name` VARCHAR(100) DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `email_type` VARCHAR(50) NOT NULL,
    `status` ENUM('Pending','Sent','Delivered','Failed','Bounced','Opened') DEFAULT 'Pending',
    `error_message` TEXT DEFAULT NULL,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `delivered_at` TIMESTAMP NULL DEFAULT NULL,
    `opened_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
