-- Migration: Remove requester table, update blood_request and donation_history
-- Run this on your existing blood_donation database

-- 0. Add myanmar_name column to users table if it doesn't exist
SET @col_exists_mn = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'myanmar_name');
SET @sql_mn = IF(@col_exists_mn = 0,
    'ALTER TABLE users ADD COLUMN myanmar_name VARCHAR(100) DEFAULT NULL AFTER status',
    'SELECT "myanmar_name column already exists"');
PREPARE stmt_mn FROM @sql_mn; EXECUTE stmt_mn; DEALLOCATE PREPARE stmt_mn;

-- 1. Update blood_request table
-- Add users_id column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'blood_request' AND COLUMN_NAME = 'users_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE blood_request ADD COLUMN users_id INT NOT NULL AFTER id',
    'SELECT "users_id column already exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add blood_groups_id column if it doesn't exist
SET @col_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'blood_request' AND COLUMN_NAME = 'blood_groups_id');
SET @sql2 = IF(@col_exists2 = 0,
    'ALTER TABLE blood_request ADD COLUMN blood_groups_id INT NOT NULL AFTER users_id',
    'SELECT "blood_groups_id column already exists"');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- Drop old requester_id column if it exists
SET @col_exists3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'blood_request' AND COLUMN_NAME = 'requester_id');
SET @sql3 = IF(@col_exists3 > 0,
    'ALTER TABLE blood_request DROP FOREIGN KEY blood_request_ibfk_1',
    'SELECT "no requester_id FK to drop"');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

SET @sql4 = IF(@col_exists3 > 0,
    'ALTER TABLE blood_request DROP COLUMN requester_id',
    'SELECT "no requester_id column to drop"');
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

-- Drop old blood_group varchar column if it exists
SET @col_exists4 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'blood_request' AND COLUMN_NAME = 'blood_group');
SET @sql5 = IF(@col_exists4 > 0,
    'ALTER TABLE blood_request DROP COLUMN blood_group',
    'SELECT "no blood_group column to drop"');
PREPARE stmt5 FROM @sql5; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;

-- Add foreign keys for blood_request if they don't exist
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'blood_request'
    AND CONSTRAINT_NAME = 'fk_br_users');
SET @sql6 = IF(@fk_exists = 0,
    'ALTER TABLE blood_request ADD CONSTRAINT fk_br_users FOREIGN KEY (users_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT "fk_br_users already exists"');
PREPARE stmt6 FROM @sql6; EXECUTE stmt6; DEALLOCATE PREPARE stmt6;

SET @fk_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'blood_request'
    AND CONSTRAINT_NAME = 'fk_br_blood_groups');
SET @sql7 = IF(@fk_exists2 = 0,
    'ALTER TABLE blood_request ADD CONSTRAINT fk_br_blood_groups FOREIGN KEY (blood_groups_id) REFERENCES blood_groups(id) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT "fk_br_blood_groups already exists"');
PREPARE stmt7 FROM @sql7; EXECUTE stmt7; DEALLOCATE PREPARE stmt7;


-- 2. Update donation_history table
-- Add users_id column if it doesn't exist
SET @col_exists5 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'donation_history' AND COLUMN_NAME = 'users_id');
SET @sql8 = IF(@col_exists5 = 0,
    'ALTER TABLE donation_history ADD COLUMN users_id INT NOT NULL AFTER donor_id',
    'SELECT "donation_history users_id column already exists"');
PREPARE stmt8 FROM @sql8; EXECUTE stmt8; DEALLOCATE PREPARE stmt8;

-- Add blood_groups_id column if it doesn't exist
SET @col_exists6 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'donation_history' AND COLUMN_NAME = 'blood_groups_id');
SET @sql9 = IF(@col_exists6 = 0,
    'ALTER TABLE donation_history ADD COLUMN blood_groups_id INT NOT NULL',
    'SELECT "donation_history blood_groups_id column already exists"');
PREPARE stmt9 FROM @sql9; EXECUTE stmt9; DEALLOCATE PREPARE stmt9;

-- Drop old requester_id column if it exists
SET @col_exists7 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'donation_history' AND COLUMN_NAME = 'requester_id');
SET @sql10 = IF(@col_exists7 > 0,
    'ALTER TABLE donation_history DROP FOREIGN KEY donation_history_ibfk_2',
    'SELECT "no dh requester_id FK to drop"');
PREPARE stmt10 FROM @sql10; EXECUTE stmt10; DEALLOCATE PREPARE stmt10;

SET @sql11 = IF(@col_exists7 > 0,
    'ALTER TABLE donation_history DROP COLUMN requester_id',
    'SELECT "no dh requester_id column to drop"');
PREPARE stmt11 FROM @sql11; EXECUTE stmt11; DEALLOCATE PREPARE stmt11;

-- Drop old blood_group varchar column if it exists
SET @col_exists8 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'donation_history' AND COLUMN_NAME = 'blood_group');
SET @sql12 = IF(@col_exists8 > 0,
    'ALTER TABLE donation_history DROP COLUMN blood_group',
    'SELECT "no dh blood_group column to drop"');
PREPARE stmt12 FROM @sql12; EXECUTE stmt12; DEALLOCATE PREPARE stmt12;

-- Add foreign keys for donation_history
SET @fk_exists3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'donation_history'
    AND CONSTRAINT_NAME = 'fk_dh_users');
SET @sql13 = IF(@fk_exists3 = 0,
    'ALTER TABLE donation_history ADD CONSTRAINT fk_dh_users FOREIGN KEY (users_id) REFERENCES users(id)',
    'SELECT "fk_dh_users already exists"');
PREPARE stmt13 FROM @sql13; EXECUTE stmt13; DEALLOCATE PREPARE stmt13;

SET @fk_exists4 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'donation_history'
    AND CONSTRAINT_NAME = 'fk_dh_blood_groups');
SET @sql14 = IF(@fk_exists4 = 0,
    'ALTER TABLE donation_history ADD CONSTRAINT fk_dh_blood_groups FOREIGN KEY (blood_groups_id) REFERENCES blood_groups(id)',
    'SELECT "fk_dh_blood_groups already exists"');
PREPARE stmt14 FROM @sql14; EXECUTE stmt14; DEALLOCATE PREPARE stmt14;


-- 3. Drop the requester table (after migrating any data if needed)
-- WARNING: Only run this if you no longer need the requester table
-- SET @req_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
--     WHERE TABLE_SCHEMA = 'blood_donation' AND TABLE_NAME = 'requester');
-- SET @sql15 = IF(@req_exists > 0,
--     'DROP TABLE IF EXISTS requester',
--     'SELECT "requester table does not exist"');
-- PREPARE stmt15 FROM @sql15; EXECUTE stmt15; DEALLOCATE PREPARE stmt15;
