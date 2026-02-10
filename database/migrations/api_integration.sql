-- =====================================================
-- ParkaLot API Integration Database Migration
-- Version: 1.0.0
-- Description: Tables for Trustpilot, Flight API, Pexels, and Live Booking integration
-- =====================================================

-- -----------------------------------------------------
-- Table: trustpilot_reviews
-- Caches Trustpilot reviews for display in footer widget
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `trustpilot_reviews` (
    `review_id` INT(11) NOT NULL AUTO_INCREMENT,
    `trustpilot_id` VARCHAR(100) NOT NULL,
    `reviewer_name` VARCHAR(255) DEFAULT NULL,
    `reviewer_avatar` VARCHAR(500) DEFAULT NULL,
    `rating` TINYINT(1) NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review_title` VARCHAR(500) DEFAULT NULL,
    `review_text` TEXT DEFAULT NULL,
    `review_date` DATETIME DEFAULT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `language` VARCHAR(10) DEFAULT 'en',
    `cached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
    PRIMARY KEY (`review_id`),
    UNIQUE KEY `idx_trustpilot_id` (`trustpilot_id`),
    KEY `idx_rating` (`rating`),
    KEY `idx_cached_at` (`cached_at`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: trustpilot_stats
-- Caches business-level Trustpilot statistics
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `trustpilot_stats` (
    `stat_id` INT(11) NOT NULL AUTO_INCREMENT,
    `business_unit_id` VARCHAR(100) NOT NULL,
    `trust_score` DECIMAL(3,2) DEFAULT NULL,
    `total_reviews` INT(11) DEFAULT 0,
    `stars_average` DECIMAL(3,2) DEFAULT NULL,
    `stars_5` INT(11) DEFAULT 0,
    `stars_4` INT(11) DEFAULT 0,
    `stars_3` INT(11) DEFAULT 0,
    `stars_2` INT(11) DEFAULT 0,
    `stars_1` INT(11) DEFAULT 0,
    `cached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 6 HOUR),
    PRIMARY KEY (`stat_id`),
    KEY `idx_business_unit` (`business_unit_id`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: flight_data
-- Caches flight information from Flight API
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `flight_data` (
    `flight_id` INT(11) NOT NULL AUTO_INCREMENT,
    `flight_number` VARCHAR(20) NOT NULL,
    `flight_iata` VARCHAR(10) DEFAULT NULL,
    `airline_code` VARCHAR(10) DEFAULT NULL,
    `airline_name` VARCHAR(255) DEFAULT NULL,
    `airline_logo` VARCHAR(500) DEFAULT NULL,
    `departure_airport` VARCHAR(10) DEFAULT NULL,
    `departure_airport_name` VARCHAR(255) DEFAULT NULL,
    `departure_city` VARCHAR(100) DEFAULT NULL,
    `departure_terminal` VARCHAR(20) DEFAULT NULL,
    `departure_gate` VARCHAR(20) DEFAULT NULL,
    `arrival_airport` VARCHAR(10) DEFAULT NULL,
    `arrival_airport_name` VARCHAR(255) DEFAULT NULL,
    `arrival_city` VARCHAR(100) DEFAULT NULL,
    `arrival_terminal` VARCHAR(20) DEFAULT NULL,
    `arrival_gate` VARCHAR(20) DEFAULT NULL,
    `scheduled_departure` DATETIME DEFAULT NULL,
    `estimated_departure` DATETIME DEFAULT NULL,
    `actual_departure` DATETIME DEFAULT NULL,
    `scheduled_arrival` DATETIME DEFAULT NULL,
    `estimated_arrival` DATETIME DEFAULT NULL,
    `actual_arrival` DATETIME DEFAULT NULL,
    `flight_status` ENUM('scheduled', 'boarding', 'departed', 'in_air', 'landed', 'arrived', 'cancelled', 'delayed', 'diverted') DEFAULT 'scheduled',
    `delay_minutes` INT(11) DEFAULT 0,
    `aircraft_type` VARCHAR(100) DEFAULT NULL,
    `flight_date` DATE DEFAULT NULL,
    `cached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 15 MINUTE),
    PRIMARY KEY (`flight_id`),
    KEY `idx_flight_number` (`flight_number`),
    KEY `idx_flight_iata` (`flight_iata`),
    KEY `idx_departure_airport` (`departure_airport`),
    KEY `idx_arrival_airport` (`arrival_airport`),
    KEY `idx_scheduled_departure` (`scheduled_departure`),
    KEY `idx_flight_date` (`flight_date`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: london_airports
-- Reference table for London airports
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `london_airports` (
    `airport_id` INT(11) NOT NULL AUTO_INCREMENT,
    `iata_code` VARCHAR(10) NOT NULL,
    `icao_code` VARCHAR(10) DEFAULT NULL,
    `airport_name` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) DEFAULT 'London',
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `timezone` VARCHAR(50) DEFAULT 'Europe/London',
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`airport_id`),
    UNIQUE KEY `idx_iata` (`iata_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert London airports
INSERT INTO `london_airports` (`iata_code`, `icao_code`, `airport_name`, `latitude`, `longitude`) VALUES
('LHR', 'EGLL', 'London Heathrow Airport', 51.4700223, -0.4542955),
('LGW', 'EGKK', 'London Gatwick Airport', 51.1536621, -0.1820629),
('STN', 'EGSS', 'London Stansted Airport', 51.8860181, 0.2388890),
('LTN', 'EGGW', 'London Luton Airport', 51.8746290, -0.3683260),
('LCY', 'EGLC', 'London City Airport', 51.5048000, 0.0495000)
ON DUPLICATE KEY UPDATE airport_name = VALUES(airport_name);

-- -----------------------------------------------------
-- Table: parking_bookings_live
-- Real-time parking bookings linked to flights
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `parking_bookings_live` (
    `booking_id` INT(11) NOT NULL AUTO_INCREMENT,
    `booking_reference` VARCHAR(20) NOT NULL,
    `reservation_id` INT(11) DEFAULT NULL,
    `user_id` INT(11) NOT NULL,
    `tfl_car_park_id` VARCHAR(100) DEFAULT NULL,
    `garage_id` INT(11) DEFAULT NULL,
    `flight_id` INT(11) DEFAULT NULL,
    `vehicle_registration` VARCHAR(20) DEFAULT NULL,
    `check_in_time` DATETIME DEFAULT NULL,
    `expected_check_out` DATETIME DEFAULT NULL,
    `actual_check_out` DATETIME DEFAULT NULL,
    `booking_status` ENUM('pending', 'confirmed', 'checked_in', 'active', 'completed', 'cancelled', 'expired', 'no_show') DEFAULT 'pending',
    `qr_code_data` VARCHAR(500) DEFAULT NULL,
    `qr_code_url` VARCHAR(500) DEFAULT NULL,
    `total_price` DECIMAL(10,2) DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'GBP',
    `payment_status` ENUM('pending', 'paid', 'refunded', 'failed') DEFAULT 'pending',
    `special_requests` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`booking_id`),
    UNIQUE KEY `idx_booking_reference` (`booking_reference`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_garage_id` (`garage_id`),
    KEY `idx_flight_id` (`flight_id`),
    KEY `idx_status` (`booking_status`),
    KEY `idx_check_in_time` (`check_in_time`),
    CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`reservation_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_booking_garage` FOREIGN KEY (`garage_id`) REFERENCES `garages`(`garage_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_booking_flight` FOREIGN KEY (`flight_id`) REFERENCES `flight_data`(`flight_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: api_cache
-- Generic API response caching
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_cache` (
    `cache_id` INT(11) NOT NULL AUTO_INCREMENT,
    `cache_key` VARCHAR(255) NOT NULL,
    `api_source` VARCHAR(50) NOT NULL,
    `endpoint` VARCHAR(500) DEFAULT NULL,
    `data` LONGTEXT NOT NULL,
    `data_hash` VARCHAR(64) DEFAULT NULL,
    `cached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `hit_count` INT(11) DEFAULT 0,
    `last_accessed` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`cache_id`),
    UNIQUE KEY `idx_cache_key` (`cache_key`),
    KEY `idx_api_source` (`api_source`),
    KEY `idx_expires_at` (`expires_at`),
    KEY `idx_last_accessed` (`last_accessed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: pexels_images
-- Cached Pexels image metadata
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pexels_images` (
    `image_id` INT(11) NOT NULL AUTO_INCREMENT,
    `pexels_id` INT(11) NOT NULL,
    `photographer` VARCHAR(255) DEFAULT NULL,
    `photographer_url` VARCHAR(500) DEFAULT NULL,
    `photographer_id` INT(11) DEFAULT NULL,
    `src_original` VARCHAR(500) DEFAULT NULL,
    `src_large2x` VARCHAR(500) DEFAULT NULL,
    `src_large` VARCHAR(500) DEFAULT NULL,
    `src_medium` VARCHAR(500) DEFAULT NULL,
    `src_small` VARCHAR(500) DEFAULT NULL,
    `src_portrait` VARCHAR(500) DEFAULT NULL,
    `src_landscape` VARCHAR(500) DEFAULT NULL,
    `src_tiny` VARCHAR(500) DEFAULT NULL,
    `alt_text` VARCHAR(500) DEFAULT NULL,
    `avg_color` VARCHAR(10) DEFAULT NULL,
    `width` INT(11) DEFAULT NULL,
    `height` INT(11) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `search_query` VARCHAR(255) DEFAULT NULL,
    `cached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`image_id`),
    UNIQUE KEY `idx_pexels_id` (`pexels_id`),
    KEY `idx_category` (`category`),
    KEY `idx_search_query` (`search_query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table: streamline_icons
-- Cached Streamline icon data for fallback
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `streamline_icons` (
    `icon_id` INT(11) NOT NULL AUTO_INCREMENT,
    `icon_name` VARCHAR(100) NOT NULL,
    `icon_family` VARCHAR(50) DEFAULT 'regular',
    `svg_content` TEXT NOT NULL,
    `viewbox` VARCHAR(50) DEFAULT '0 0 24 24',
    `cached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`icon_id`),
    UNIQUE KEY `idx_icon_name_family` (`icon_name`, `icon_family`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Stored Procedure: Clean expired cache entries
-- -----------------------------------------------------
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `CleanExpiredCache`()
BEGIN
    DELETE FROM `api_cache` WHERE `expires_at` < NOW();
    DELETE FROM `trustpilot_reviews` WHERE `expires_at` < NOW();
    DELETE FROM `trustpilot_stats` WHERE `expires_at` < NOW();
    DELETE FROM `flight_data` WHERE `expires_at` < NOW();
END //
DELIMITER ;

-- -----------------------------------------------------
-- Event: Auto-clean expired cache (runs every hour)
-- -----------------------------------------------------
-- Note: Requires event_scheduler to be enabled in MySQL
-- SET GLOBAL event_scheduler = ON;
CREATE EVENT IF NOT EXISTS `auto_clean_cache`
ON SCHEDULE EVERY 1 HOUR
DO CALL CleanExpiredCache();
