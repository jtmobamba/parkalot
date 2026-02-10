-- Migration: Customer Spaces, Bookings, and Payment Tables
-- Version: 004
-- Date: 2026-02-09
-- Description: Adds tables for customer space sharing, space bookings, and payment tracking.
--              Also enhances parking_bookings_live for airport booking with flight linking.

-- =====================================================
-- Customer Spaces Table
-- Allows customers to list their parking spaces for rent
-- =====================================================
CREATE TABLE IF NOT EXISTS `customer_spaces` (
  `space_id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `space_name` varchar(255) NOT NULL,
  `space_type` enum('driveway','garage','parking_spot','car_park') DEFAULT 'driveway',
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `postcode` varchar(20) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amenities` text DEFAULT NULL COMMENT 'JSON array of amenities: covered, cctv, ev_charging, 24_7_access, disabled_access, security_lighting',
  `instructions` text DEFAULT NULL COMMENT 'Access instructions for renters',
  `price_per_hour` decimal(10,2) NOT NULL,
  `price_per_day` decimal(10,2) DEFAULT NULL,
  `min_booking_hours` int(11) DEFAULT 1,
  `max_booking_days` int(11) DEFAULT 30,
  `photos` text DEFAULT NULL COMMENT 'JSON array of photo URLs',
  `status` enum('pending','active','paused','rejected') DEFAULT 'pending',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `total_bookings` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT NULL,
  `review_count` int(11) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`space_id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_city` (`city`),
  KEY `idx_postcode` (`postcode`),
  KEY `idx_location` (`latitude`, `longitude`),
  KEY `idx_price` (`price_per_hour`),
  CONSTRAINT `fk_customer_spaces_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Customer Space Availability Table
-- Tracks when spaces are available for booking
-- =====================================================
CREATE TABLE IF NOT EXISTS `customer_space_availability` (
  `availability_id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`availability_id`),
  KEY `idx_space_day` (`space_id`, `day_of_week`),
  CONSTRAINT `fk_availability_space` FOREIGN KEY (`space_id`) REFERENCES `customer_spaces` (`space_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Customer Space Bookings Table
-- Bookings made on customer-listed spaces
-- =====================================================
CREATE TABLE IF NOT EXISTS `customer_space_bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `renter_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `vehicle_reg` varchar(20) DEFAULT NULL,
  `vehicle_make` varchar(100) DEFAULT NULL,
  `vehicle_model` varchar(100) DEFAULT NULL,
  `vehicle_color` varchar(50) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `platform_fee` decimal(10,2) DEFAULT 0.00 COMMENT '15% platform fee',
  `owner_payout` decimal(10,2) DEFAULT 0.00 COMMENT 'Amount paid to space owner',
  `booking_status` enum('pending','confirmed','active','completed','cancelled','disputed') DEFAULT 'pending',
  `payment_status` enum('pending','paid','refunded','partial_refund') DEFAULT 'pending',
  `payment_id` int(11) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL,
  `cancelled_by` enum('renter','owner','system') DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `renter_notes` text DEFAULT NULL,
  `owner_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`booking_id`),
  KEY `idx_space_id` (`space_id`),
  KEY `idx_renter_id` (`renter_id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_booking_status` (`booking_status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_dates` (`start_time`, `end_time`),
  KEY `idx_stripe_intent` (`stripe_payment_intent_id`),
  CONSTRAINT `fk_space_booking_space` FOREIGN KEY (`space_id`) REFERENCES `customer_spaces` (`space_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_space_booking_renter` FOREIGN KEY (`renter_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_space_booking_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Space Reviews Table
-- Reviews for customer-listed spaces
-- =====================================================
CREATE TABLE IF NOT EXISTS `customer_space_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `response_text` text DEFAULT NULL COMMENT 'Owner response to review',
  `response_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `idx_booking_review` (`booking_id`),
  KEY `idx_space_id` (`space_id`),
  KEY `idx_reviewer_id` (`reviewer_id`),
  CONSTRAINT `fk_space_review_space` FOREIGN KEY (`space_id`) REFERENCES `customer_spaces` (`space_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_space_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `customer_space_bookings` (`booking_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_space_review_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Payments Table
-- Central payment tracking for all booking types
-- =====================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `booking_type` enum('garage','customer_space','airport') NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'GBP',
  `payment_method` enum('stripe','paypal','bank_transfer') DEFAULT 'stripe',
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_charge_id` varchar(255) DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','succeeded','failed','cancelled','refunded') DEFAULT 'pending',
  `failure_reason` varchar(500) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `metadata` text DEFAULT NULL COMMENT 'JSON additional payment data',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_booking` (`booking_type`, `booking_id`),
  KEY `idx_stripe_intent` (`stripe_payment_intent_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Owner Payouts Table
-- Track payouts to space owners
-- =====================================================
CREATE TABLE IF NOT EXISTS `owner_payouts` (
  `payout_id` int(11) NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'GBP',
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `stripe_transfer_id` varchar(255) DEFAULT NULL,
  `stripe_payout_id` varchar(255) DEFAULT NULL,
  `bank_account_last4` varchar(4) DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `bookings_count` int(11) DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `failure_reason` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payout_id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_period` (`period_start`, `period_end`),
  CONSTRAINT `fk_payout_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Enhance parking_bookings_live for Airport Bookings
-- Add flight linking and vehicle details
-- =====================================================
-- Check if columns exist before adding (safe for re-running)
SET @dbname = DATABASE();

-- Add airport_code column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'airport_code');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `airport_code` varchar(5) DEFAULT NULL AFTER `booking_status`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add terminal column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'terminal');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `terminal` varchar(10) DEFAULT NULL AFTER `airport_code`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add outbound_flight_number column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'outbound_flight_number');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `outbound_flight_number` varchar(20) DEFAULT NULL AFTER `terminal`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add return_flight_number column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'return_flight_number');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `return_flight_number` varchar(20) DEFAULT NULL AFTER `outbound_flight_number`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add return_date column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'return_date');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `return_date` datetime DEFAULT NULL AFTER `return_flight_number`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add vehicle_make column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'vehicle_make');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `vehicle_make` varchar(100) DEFAULT NULL AFTER `return_date`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add vehicle_model column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'vehicle_model');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `vehicle_model` varchar(100) DEFAULT NULL AFTER `vehicle_make`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add vehicle_color column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'vehicle_color');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `vehicle_color` varchar(50) DEFAULT NULL AFTER `vehicle_model`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add stripe_payment_intent_id column
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'parking_bookings_live' AND COLUMN_NAME = 'stripe_payment_intent_id');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `parking_bookings_live` ADD COLUMN `stripe_payment_intent_id` varchar(255) DEFAULT NULL AFTER `vehicle_color`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for the new columns
CREATE INDEX IF NOT EXISTS `idx_airport_code` ON `parking_bookings_live` (`airport_code`);
CREATE INDEX IF NOT EXISTS `idx_flight_number` ON `parking_bookings_live` (`return_flight_number`);

-- =====================================================
-- Add Stripe fields to users table for Connect
-- =====================================================
SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'stripe_customer_id');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `stripe_customer_id` varchar(255) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'users' AND COLUMN_NAME = 'stripe_connect_id');
SET @sql = IF(@columnExists = 0,
    'ALTER TABLE `users` ADD COLUMN `stripe_connect_id` varchar(255) DEFAULT NULL COMMENT "For space owners to receive payouts"',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Insert sample customer spaces for demo
-- =====================================================
INSERT INTO `customer_spaces` (`owner_id`, `space_name`, `space_type`, `address_line1`, `city`, `postcode`, `latitude`, `longitude`, `description`, `amenities`, `price_per_hour`, `price_per_day`, `status`, `average_rating`, `total_bookings`)
SELECT
    u.user_id,
    'Private Driveway Near City Centre',
    'driveway',
    '15 Maple Street',
    'London',
    'W1K 1AB',
    51.5123,
    -0.1567,
    'Secure private driveway in quiet residential area. Perfect for city centre visits. Easy access from main roads.',
    '["covered","cctv","security_lighting"]',
    3.50,
    25.00,
    'active',
    4.5,
    12
FROM `users` u WHERE u.role = 'customer' LIMIT 1
ON DUPLICATE KEY UPDATE `space_id` = `space_id`;

INSERT INTO `customer_spaces` (`owner_id`, `space_name`, `space_type`, `address_line1`, `city`, `postcode`, `latitude`, `longitude`, `description`, `amenities`, `price_per_hour`, `price_per_day`, `status`, `average_rating`, `total_bookings`)
SELECT
    u.user_id,
    'Covered Garage Space - Shoreditch',
    'garage',
    '42 Brick Lane',
    'London',
    'E1 6RF',
    51.5223,
    -0.0723,
    'Covered garage space in Shoreditch. Protected from weather, great for longer stays. EV charging available.',
    '["covered","cctv","ev_charging","24_7_access"]',
    5.00,
    35.00,
    'active',
    4.8,
    28
FROM `users` u WHERE u.role = 'customer' LIMIT 1
ON DUPLICATE KEY UPDATE `space_id` = `space_id`;

-- Log the migration
INSERT INTO `activity_logs` (`role`, `action`, `description`, `ip_address`)
VALUES ('system', 'migration', 'Applied migration 004: Customer spaces and payments tables', 'localhost');
