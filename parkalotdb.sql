-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 06, 2026 at 12:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `parkalotdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_contracts`
--

CREATE TABLE `employee_contracts` (
  `contract_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_type` enum('employee','senior_employee','manager') DEFAULT 'employee',
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `hire_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `contract_status` enum('active','terminated','suspended','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_contracts`
--

INSERT INTO `employee_contracts` (`contract_id`, `user_id`, `employee_type`, `department`, `position`, `salary`, `hire_date`, `start_date`, `end_date`, `contract_status`, `created_at`) VALUES
(1, 1, 'manager', 'management', 'General Manager', 55000.00, '2023-01-01', '2023-01-01', NULL, 'active', '2026-02-05 22:30:09'),
(2, 2, 'employee', 'operations', 'Parking Attendant', 25000.00, '2023-06-01', '2023-06-01', NULL, 'active', '2026-02-05 22:30:09'),
(3, 3, 'senior_employee', 'customer_service', 'Senior Customer Service Rep', 35000.00, '2023-03-01', '2023-03-01', NULL, 'active', '2026-02-05 22:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `garages`
--

CREATE TABLE `garages` (
  `garage_id` int(11) NOT NULL,
  `garage_name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `total_spaces` int(11) NOT NULL,
  `price_per_hour` decimal(10,2) NOT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `amenities` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `garages`
--

INSERT INTO `garages` (`garage_id`, `garage_name`, `location`, `total_spaces`, `price_per_hour`, `rating`, `amenities`, `image_url`, `latitude`, `longitude`, `created_at`) VALUES
(1, 'City Center Garage', 'Manchester City Center', 100, 5.50, 4.50, 'CCTV,24/7 Access,EV Charging', NULL, 53.48080000, -2.24260000, '2026-02-05 22:30:09'),
(2, 'Airport Parking', 'Manchester Airport', 500, 3.00, 4.20, 'Shuttle Service,Covered Parking', NULL, 53.35390000, -2.27500000, '2026-02-05 22:30:09'),
(3, 'Suburban Safe Park', 'Stockport', 50, 2.50, 4.80, 'Security Guard,Well Lit', NULL, 53.41060000, -2.15750000, '2026-02-05 22:30:09'),
(4, 'Business District Garage', 'Salford Quays', 200, 6.00, 4.30, 'CCTV,EV Charging,Car Wash', NULL, 53.47230000, -2.29300000, '2026-02-05 22:30:09'),
(5, 'Budget Parking Lot', 'Oldham', 75, 1.50, 3.90, 'Basic Security,Outdoor', NULL, 53.54440000, -2.11690000, '2026-02-05 22:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `garage_reviews`
--

CREATE TABLE `garage_reviews` (
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `application_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position_applied` varchar(100) NOT NULL,
  `cv_file_path` varchar(500) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('pending','reviewing','interviewed','accepted','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'GBP',
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_charge_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','succeeded','failed','refunded') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL COMMENT 'User ID of the report author',
  `department` varchar(50) DEFAULT NULL COMMENT 'Department the report relates to',
  `employee_id` int(11) DEFAULT NULL COMMENT 'Specific employee the report is about (optional)',
  `report_type` enum('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL COMMENT 'Report content - up to 100,000 characters',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `garage_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','completed','cancelled','refunded') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','employee','senior_employee','manager','vehicle_inspector') DEFAULT 'customer',
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `email_verified`, `created_at`, `last_login`) VALUES
(1, 'Admin Manager', 'manager@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, '2026-02-05 22:30:09', NULL),
(2, 'John Smith', 'employee@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 1, '2026-02-05 22:30:09', NULL),
(3, 'Sarah Johnson', 'senior@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'senior_employee', 1, '2026-02-05 22:30:09', NULL),
(4, 'Vehicle Inspector', 'inspector@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vehicle_inspector', 1, '2026-02-05 22:30:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preferred_location` varchar(255) DEFAULT NULL,
  `max_price_per_hour` decimal(10,2) DEFAULT NULL,
  `preferred_amenities` text DEFAULT NULL,
  `max_hours` int(11) DEFAULT NULL COMMENT 'Maximum working hours per day',
  `operation_status` enum('completed','incomplete','breaktime') DEFAULT 'incomplete' COMMENT 'Current operation status',
  `rating` int(11) DEFAULT NULL COMMENT 'Performance rating 1-5',
  `current_shift` varchar(50) DEFAULT 'Day Shift' COMMENT 'Current shift assignment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`preference_id`, `user_id`, `preferred_location`, `max_price_per_hour`, `preferred_amenities`, `max_hours`, `operation_status`, `rating`, `current_shift`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, NULL, NULL, NULL, 'completed', NULL, 'Day Shift', '2026-02-05 22:30:09', '2026-02-05 22:30:09'),
(2, 2, NULL, NULL, NULL, NULL, 'incomplete', NULL, 'Morning Shift', '2026-02-05 22:30:09', '2026-02-05 22:30:09'),
(3, 3, NULL, NULL, NULL, NULL, 'completed', NULL, 'Day Shift', '2026-02-05 22:30:09', '2026-02-05 22:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_vehicles`
--

CREATE TABLE `user_vehicles` (
  `user_vehicle_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `ownership_type` enum('owner','driver','authorized') DEFAULT 'owner',
  `is_primary` tinyint(1) DEFAULT 0,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `make` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `vehicle_type` enum('car','motorcycle','van','truck','suv','other') DEFAULT 'car',
  `owner_name` varchar(255) DEFAULT NULL,
  `owner_contact` varchar(100) DEFAULT NULL,
  `registration_status` enum('valid','expired','suspended','unknown') DEFAULT 'unknown',
  `insurance_status` enum('valid','expired','none','unknown') DEFAULT 'unknown',
  `mot_status` enum('valid','expired','exempt','unknown') DEFAULT 'unknown',
  `mot_expiry_date` date DEFAULT NULL,
  `tax_status` enum('valid','expired','exempt','sorn','unknown') DEFAULT 'unknown',
  `tax_due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_inspection_date` timestamp NULL DEFAULT NULL,
  `inspected_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `license_plate`, `make`, `model`, `color`, `year`, `vehicle_type`, `owner_name`, `owner_contact`, `registration_status`, `insurance_status`, `mot_status`, `mot_expiry_date`, `tax_status`, `tax_due_date`, `notes`, `last_inspection_date`, `inspected_by`, `created_at`, `updated_at`) VALUES
(1, 'AB12 CDE', 'Toyota', 'Corolla', 'Silver', 2020, 'car', 'John Smith', NULL, 'valid', 'valid', 'valid', NULL, 'valid', NULL, NULL, NULL, NULL, '2026-02-05 22:30:09', '2026-02-05 22:30:09'),
(2, 'XY34 FGH', 'Ford', 'Focus', 'Blue', 2019, 'car', 'Jane Doe', NULL, 'valid', 'valid', 'expired', NULL, 'valid', NULL, NULL, NULL, NULL, '2026-02-05 22:30:09', '2026-02-05 22:30:09'),
(3, 'MN56 IJK', 'BMW', '3 Series', 'Black', 2021, 'car', 'Bob Wilson', NULL, 'valid', 'valid', 'valid', NULL, 'valid', NULL, NULL, NULL, NULL, '2026-02-05 22:30:09', '2026-02-05 22:30:09'),
(4, 'PQ78 LMN', 'Honda', 'CR-V', 'White', 2018, 'suv', 'Alice Brown', NULL, 'valid', 'expired', 'valid', NULL, 'expired', NULL, NULL, NULL, NULL, '2026-02-05 22:30:09', '2026-02-05 22:30:09'),
(5, 'RS90 OPQ', 'Mercedes', 'Sprinter', 'White', 2017, 'van', 'Delivery Co Ltd', NULL, 'valid', 'valid', 'valid', NULL, 'valid', NULL, NULL, NULL, NULL, '2026-02-05 22:30:09', '2026-02-05 22:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_inspections`
--

CREATE TABLE `vehicle_inspections` (
  `inspection_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `inspector_id` int(11) NOT NULL,
  `garage_id` int(11) DEFAULT NULL,
  `inspection_type` enum('entry','exit','routine','incident','verification') DEFAULT 'routine',
  `inspection_result` enum('pass','fail','warning','pending') DEFAULT 'pending',
  `damage_detected` tinyint(1) DEFAULT 0,
  `damage_description` text DEFAULT NULL,
  `photo_urls` text DEFAULT NULL,
  `location_spotted` varchar(255) DEFAULT NULL,
  `inspection_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_otp_code` (`otp_code`);

--
-- Indexes for table `employee_contracts`
--
ALTER TABLE `employee_contracts`
  ADD PRIMARY KEY (`contract_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_contract_status` (`contract_status`);

--
-- Indexes for table `garages`
--
ALTER TABLE `garages`
  ADD PRIMARY KEY (`garage_id`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `garage_reviews`
--
ALTER TABLE `garage_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reservation_id` (`reservation_id`),
  ADD KEY `idx_garage_id` (`garage_id`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_position` (`position_applied`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_reservation_id` (`reservation_id`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `idx_author` (`author_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_report_type` (`report_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_garage_id` (`garage_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_start_time` (`start_time`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD UNIQUE KEY `unique_user_preference` (`user_id`);

--
-- Indexes for table `user_vehicles`
--
ALTER TABLE `user_vehicles`
  ADD PRIMARY KEY (`user_vehicle_id`),
  ADD UNIQUE KEY `unique_user_vehicle` (`user_id`,`vehicle_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `license_plate` (`license_plate`),
  ADD KEY `inspected_by` (`inspected_by`),
  ADD KEY `idx_license_plate` (`license_plate`),
  ADD KEY `idx_registration_status` (`registration_status`),
  ADD KEY `idx_vehicle_type` (`vehicle_type`);

--
-- Indexes for table `vehicle_inspections`
--
ALTER TABLE `vehicle_inspections`
  ADD PRIMARY KEY (`inspection_id`),
  ADD KEY `garage_id` (`garage_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_inspector_id` (`inspector_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_contracts`
--
ALTER TABLE `employee_contracts`
  MODIFY `contract_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `garages`
--
ALTER TABLE `garages`
  MODIFY `garage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `garage_reviews`
--
ALTER TABLE `garage_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_vehicles`
--
ALTER TABLE `user_vehicles`
  MODIFY `user_vehicle_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicle_inspections`
--
ALTER TABLE `vehicle_inspections`
  MODIFY `inspection_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_contracts`
--
ALTER TABLE `employee_contracts`
  ADD CONSTRAINT `employee_contracts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `garage_reviews`
--
ALTER TABLE `garage_reviews`
  ADD CONSTRAINT `garage_reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `garage_reviews_ibfk_2` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`garage_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `garage_reviews_ibfk_3` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE SET NULL;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`garage_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_vehicles`
--
ALTER TABLE `user_vehicles`
  ADD CONSTRAINT `user_vehicles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_vehicles_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_vehicles_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicle_inspections`
--
ALTER TABLE `vehicle_inspections`
  ADD CONSTRAINT `vehicle_inspections_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehicle_inspections_ibfk_2` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehicle_inspections_ibfk_3` FOREIGN KEY (`garage_id`) REFERENCES `garages` (`garage_id`) ON DELETE SET NULL;

-- --------------------------------------------------------
--
-- Table structure for table `flight_data`
-- Caches flight information for live bookings
--

CREATE TABLE IF NOT EXISTS `flight_data` (
  `flight_id` int(11) NOT NULL AUTO_INCREMENT,
  `flight_number` varchar(20) NOT NULL,
  `flight_iata` varchar(10) DEFAULT NULL,
  `airline_code` varchar(10) DEFAULT NULL,
  `airline_name` varchar(255) DEFAULT NULL,
  `airline_logo` varchar(500) DEFAULT NULL,
  `departure_airport` varchar(10) DEFAULT NULL,
  `departure_airport_name` varchar(255) DEFAULT NULL,
  `departure_city` varchar(100) DEFAULT NULL,
  `departure_terminal` varchar(20) DEFAULT NULL,
  `departure_gate` varchar(20) DEFAULT NULL,
  `arrival_airport` varchar(10) DEFAULT NULL,
  `arrival_airport_name` varchar(255) DEFAULT NULL,
  `arrival_city` varchar(100) DEFAULT NULL,
  `arrival_terminal` varchar(20) DEFAULT NULL,
  `arrival_gate` varchar(20) DEFAULT NULL,
  `scheduled_departure` datetime DEFAULT NULL,
  `estimated_departure` datetime DEFAULT NULL,
  `actual_departure` datetime DEFAULT NULL,
  `scheduled_arrival` datetime DEFAULT NULL,
  `estimated_arrival` datetime DEFAULT NULL,
  `actual_arrival` datetime DEFAULT NULL,
  `flight_status` enum('scheduled','boarding','departed','in_air','landed','arrived','cancelled','delayed','diverted') DEFAULT 'scheduled',
  `delay_minutes` int(11) DEFAULT 0,
  `aircraft_type` varchar(100) DEFAULT NULL,
  `flight_date` date DEFAULT NULL,
  `cached_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 15 minute),
  PRIMARY KEY (`flight_id`),
  KEY `idx_flight_number` (`flight_number`),
  KEY `idx_departure_airport` (`departure_airport`),
  KEY `idx_arrival_airport` (`arrival_airport`),
  KEY `idx_scheduled_departure` (`scheduled_departure`),
  KEY `idx_flight_date` (`flight_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `parking_bookings_live`
-- Real-time parking bookings linked to flights
--

CREATE TABLE IF NOT EXISTS `parking_bookings_live` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_reference` varchar(20) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `tfl_car_park_id` varchar(100) DEFAULT NULL,
  `garage_id` int(11) DEFAULT NULL,
  `flight_id` int(11) DEFAULT NULL,
  `vehicle_registration` varchar(20) DEFAULT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `expected_check_out` datetime DEFAULT NULL,
  `actual_check_out` datetime DEFAULT NULL,
  `booking_status` enum('pending','confirmed','checked_in','active','completed','cancelled','expired','no_show') DEFAULT 'pending',
  `qr_code_data` varchar(500) DEFAULT NULL,
  `qr_code_url` varchar(500) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'GBP',
  `payment_status` enum('pending','paid','refunded','failed') DEFAULT 'pending',
  `special_requests` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`booking_id`),
  UNIQUE KEY `idx_booking_reference` (`booking_reference`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_garage_id` (`garage_id`),
  KEY `idx_flight_id` (`flight_id`),
  KEY `idx_status` (`booking_status`),
  KEY `idx_check_in_time` (`check_in_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Sample data for live bookings demo
--

INSERT INTO `flight_data` (`flight_number`, `airline_code`, `airline_name`, `departure_airport`, `departure_city`, `arrival_airport`, `arrival_city`, `scheduled_departure`, `flight_status`) VALUES
('BA115', 'BA', 'British Airways', 'LHR', 'London', 'JFK', 'New York', DATE_ADD(NOW(), INTERVAL 3 HOUR), 'scheduled'),
('VS3', 'VS', 'Virgin Atlantic', 'LHR', 'London', 'JFK', 'New York', DATE_ADD(NOW(), INTERVAL 4 HOUR), 'scheduled'),
('EK5', 'EK', 'Emirates', 'LHR', 'London', 'DXB', 'Dubai', DATE_ADD(NOW(), INTERVAL 2 HOUR), 'boarding'),
('BA178', 'BA', 'British Airways', 'LGW', 'London', 'LAX', 'Los Angeles', DATE_ADD(NOW(), INTERVAL 5 HOUR), 'scheduled'),
('U28721', 'U2', 'easyJet', 'STN', 'London', 'CDG', 'Paris', DATE_ADD(NOW(), INTERVAL 1 HOUR), 'boarding');

INSERT INTO `parking_bookings_live` (`booking_reference`, `user_id`, `tfl_car_park_id`, `flight_id`, `vehicle_registration`, `check_in_time`, `expected_check_out`, `booking_status`, `total_price`, `payment_status`, `created_at`) VALUES
('PL-LHR-001', 1, 'CarParks_800491', 1, 'AB12 CDE', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'checked_in', 89.50, 'paid', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('PL-LHR-002', 1, 'CarParks_800492', 2, 'XY34 FGH', DATE_ADD(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 5 DAY), 'confirmed', 65.00, 'paid', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
('PL-LHR-003', 1, 'CarParks_800491', 3, 'MN56 JKL', NOW(), DATE_ADD(NOW(), INTERVAL 10 DAY), 'active', 125.00, 'paid', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('PL-LGW-001', 1, 'CarParks_800493', 4, 'PQ78 RST', DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 3 DAY), 'confirmed', 45.00, 'pending', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
('PL-STN-001', 1, 'CarParks_800494', 5, 'UV90 WXY', NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'checked_in', 28.50, 'paid', DATE_SUB(NOW(), INTERVAL 1 HOUR));

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
