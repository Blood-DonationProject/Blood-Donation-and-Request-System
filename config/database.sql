-- ========================================================
-- Blood Donation and Request System - Database Schema
-- Charset: utf8mb4, Engine: InnoDB (Full Foreign Key & Transaction Support)
-- ========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'User') NOT NULL DEFAULT 'User',
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `myanmar_name` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(15) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `reset_token` VARCHAR(255) DEFAULT NULL,
    `reset_expires_at` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `last_activity` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table structure for `blood_groups`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `blood_gp_name` VARCHAR(10) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Blood Group Reference Data
INSERT IGNORE INTO `blood_groups` (`blood_gp_name`) VALUES
('A+'), ('A-'), ('B+'), ('B-'), ('AB+'), ('AB-'), ('O+'), ('O-');

-- Initial Admin Account (Default password: password or 123456)
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`, `status`) VALUES
('admin', 'bloodcommunicationsystem12@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Active');

-- --------------------------------------------------------
-- 3. Table structure for `donor`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donor` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `age` INT NOT NULL,
    `blood_groups` VARCHAR(5) NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT NOT NULL,
    `weight` DECIMAL(5,2) NOT NULL,
    `last_donation_date` DATE DEFAULT NULL,
    `available_status` ENUM('Available', 'Unavailable') DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_donor_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    `status` ENUM('Pending','Approved','Assigned','Completed','Rejected','Accepted','Received','Cancelled','Expired') DEFAULT 'Pending',
    `Urgency` ENUM('Normal', 'Urgent') NOT NULL DEFAULT 'Normal',
    `assigned_donor_id` INT DEFAULT NULL,
    `donor_id` INT DEFAULT NULL,
    `donor_accepted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_br_users`
        FOREIGN KEY (`users_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_br_blood_groups`
        FOREIGN KEY (`blood_groups_id`) REFERENCES `blood_groups`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_br_assigned_donor`
        FOREIGN KEY (`assigned_donor_id`) REFERENCES `donor`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table structure for `donor_assignments`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donor_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `donor_id` INT NOT NULL,
    `assigned_by` INT NOT NULL,
    `status` ENUM('Assigned','Accepted','Rejected','Received','Completed','Cancelled') DEFAULT 'Assigned',
    `responded_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_da_request`
        FOREIGN KEY (`request_id`) REFERENCES `blood_request`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_da_donor`
        FOREIGN KEY (`donor_id`) REFERENCES `donor`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_da_assigned_by`
        FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table structure for `donation_history`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donation_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `donor_id` INT NOT NULL,
    `users_id` INT NOT NULL DEFAULT 0,
    `request_id` INT NOT NULL,
    `blood_groups_id` INT NOT NULL,
    `units` INT NOT NULL,
    `donation_date` DATE NOT NULL,
    `status` ENUM('Completed') DEFAULT 'Completed',
    CONSTRAINT `fk_dh_donor`
        FOREIGN KEY (`donor_id`) REFERENCES `donor`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dh_users`
        FOREIGN KEY (`users_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dh_request`
        FOREIGN KEY (`request_id`) REFERENCES `blood_request`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dh_blood_groups`
        FOREIGN KEY (`blood_groups_id`) REFERENCES `blood_groups`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table structure for `notifications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `request_id` INT DEFAULT NULL,
    `assignment_id` INT DEFAULT NULL,
    `donor_id` INT DEFAULT NULL,
    `related_user_id` INT DEFAULT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notif_request`
        FOREIGN KEY (`request_id`) REFERENCES `blood_request`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notif_assignment`
        FOREIGN KEY (`assignment_id`) REFERENCES `donor_assignments`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table structure for `email_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `notification_id` INT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `related_id` INT DEFAULT NULL,
    `recipient_email` VARCHAR(100) NOT NULL,
    `recipient_name` VARCHAR(100) DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `email_type` VARCHAR(50) NOT NULL,
    `status` ENUM('Pending', 'Sent', 'Delivered', 'Failed', 'Bounced', 'Opened') DEFAULT 'Pending',
    `error_message` TEXT DEFAULT NULL,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `delivered_at` TIMESTAMP NULL DEFAULT NULL,
    `opened_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_email_notif`
        FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_email_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
