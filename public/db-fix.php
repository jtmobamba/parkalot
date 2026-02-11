<?php
/**
 * Database Fix Script for ParkaLot
 * Creates missing tables required for the customer spaces feature
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>ParkaLot Database Fix</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
    .btn:hover { background: #2980b9; }
</style>";

require_once '../config/database.php';

try {
    $db = getDbConnection();
    echo "<p class='success'>Connected to database.</p>";
} catch (Exception $e) {
    echo "<p class='error'>Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

$tablesCreated = 0;
$errors = [];

// Create customer_spaces table
echo "<h2>Creating customer_spaces table...</h2>";
try {
    $sql = "
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
      `amenities` text DEFAULT NULL COMMENT 'JSON array of amenities',
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
      KEY `idx_price` (`price_per_hour`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sql);
    echo "<p class='success'>customer_spaces table created/verified.</p>";
    $tablesCreated++;
} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $errors[] = $e->getMessage();
}

// Create customer_space_bookings table
echo "<h2>Creating customer_space_bookings table...</h2>";
try {
    $sql = "
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
      KEY `idx_stripe_intent` (`stripe_payment_intent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sql);
    echo "<p class='success'>customer_space_bookings table created/verified.</p>";
    $tablesCreated++;
} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $errors[] = $e->getMessage();
}

// Create owner_payouts table
echo "<h2>Creating owner_payouts table...</h2>";
try {
    $sql = "
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
      KEY `idx_period` (`period_start`, `period_end`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sql);
    echo "<p class='success'>owner_payouts table created/verified.</p>";
    $tablesCreated++;
} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $errors[] = $e->getMessage();
}

// Create payments table
echo "<h2>Creating payments table...</h2>";
try {
    $sql = "
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
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sql);
    echo "<p class='success'>payments table created/verified.</p>";
    $tablesCreated++;
} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $errors[] = $e->getMessage();
}

// Add profile columns to users table
echo "<h2>Adding profile columns to users table...</h2>";
try {
    // Check if columns exist first
    $columnsToAdd = [
        'phone' => "ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email`",
        'address' => "ALTER TABLE `users` ADD COLUMN `address` VARCHAR(255) DEFAULT NULL AFTER `phone`",
        'city' => "ALTER TABLE `users` ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `address`",
        'postal_code' => "ALTER TABLE `users` ADD COLUMN `postal_code` VARCHAR(20) DEFAULT NULL AFTER `city`"
    ];

    $stmt = $db->query("DESCRIBE users");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $columnsAdded = 0;
    foreach ($columnsToAdd as $column => $sql) {
        if (!in_array($column, $existingColumns)) {
            try {
                $db->exec($sql);
                echo "<p class='success'>Added column: {$column}</p>";
                $columnsAdded++;
            } catch (Exception $e) {
                echo "<p class='warning'>Could not add column {$column}: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='success'>Column {$column} already exists.</p>";
        }
    }

    if ($columnsAdded > 0) {
        echo "<p class='success'>{$columnsAdded} new column(s) added to users table.</p>";
    } else {
        echo "<p class='success'>All profile columns already exist in users table.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error modifying users table: " . htmlspecialchars($e->getMessage()) . "</p>";
    $errors[] = $e->getMessage();
}

// Summary
echo "<hr>";
echo "<h2>Summary</h2>";
if (empty($errors)) {
    echo "<p class='success'>All {$tablesCreated} tables created/verified successfully!</p>";
    echo "<p>You should now be able to create listings on the <a href='list-your-space.html'>List Your Space</a> page.</p>";
} else {
    echo "<p class='error'>Some errors occurred:</p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}

// Verify table existence
echo "<h2>Verification</h2>";
$tables = ['customer_spaces', 'customer_space_bookings', 'owner_payouts', 'payments'];
echo "<ul>";
foreach ($tables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
    $exists = $stmt->rowCount() > 0;
    $status = $exists ? "<span class='success'>OK</span>" : "<span class='error'>MISSING</span>";
    echo "<li>{$table}: {$status}</li>";
}
echo "</ul>";

echo "<p><a href='db-check.php' class='btn'>Run Full Diagnostic</a></p>";
echo "<p><a href='list-your-space.html' class='btn'>Go to List Your Space</a></p>";
?>
