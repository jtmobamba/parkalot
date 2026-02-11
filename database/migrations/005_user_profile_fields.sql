-- Migration: User Profile Fields
-- Version: 005
-- Date: 2026-02-11
-- Description: Adds profile fields to users table for storing phone, address, city, and postal code.

SET @dbname = DATABASE();

-- =====================================================
-- Add phone column to users table
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `phone` varchar(30) DEFAULT NULL AFTER `email`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add address column to users table
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'address');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `address` varchar(255) DEFAULT NULL AFTER `phone`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add city column to users table
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'city');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `city` varchar(100) DEFAULT NULL AFTER `address`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add postal_code column to users table
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'postal_code');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `postal_code` varchar(20) DEFAULT NULL AFTER `city`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add stripe_customer_id if not exists (from migration 004)
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'stripe_customer_id');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `stripe_customer_id` varchar(255) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add stripe_connect_id if not exists (from migration 004)
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'stripe_connect_id');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `stripe_connect_id` varchar(255) DEFAULT NULL COMMENT "For space owners to receive payouts"',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add profile_picture column for future use
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_picture');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `profile_picture` varchar(500) DEFAULT NULL COMMENT "URL to profile picture"',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Add date_of_birth column for future use
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'date_of_birth');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `date_of_birth` date DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Log the migration (if activity_logs table exists and has proper structure)
-- =====================================================
-- Note: Skipping activity log insertion as it may have required fields

-- Verification: Show updated table structure
SELECT 'Migration 005 completed. Users table now includes profile fields.' AS status;
