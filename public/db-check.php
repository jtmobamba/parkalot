<?php
/**
 * Database Diagnostic Script for ParkaLot
 * Run this to verify database tables and connections
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>ParkaLot Database Diagnostic</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f5f5f5; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
</style>";

// Database connection
require_once '../config/database.php';

echo "<h2>1. Database Connection</h2>";
try {
    $db = getDbConnection();
    echo "<p class='success'>Connected to database successfully!</p>";
} catch (Exception $e) {
    echo "<p class='error'>Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Check if customer_spaces table exists
echo "<h2>2. Required Tables</h2>";
$requiredTables = [
    'users' => 'Core user accounts table',
    'customer_spaces' => 'Customer space listings table',
    'customer_space_bookings' => 'Space booking records',
    'owner_payouts' => 'Owner payout tracking',
    'payments' => 'Payment transactions'
];

echo "<table><tr><th>Table</th><th>Status</th><th>Description</th></tr>";
foreach ($requiredTables as $table => $description) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->rowCount() > 0;
        $status = $exists ? "<span class='success'>EXISTS</span>" : "<span class='error'>MISSING</span>";
        echo "<tr><td>{$table}</td><td>{$status}</td><td>{$description}</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>{$table}</td><td><span class='error'>ERROR</span></td><td>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
}
echo "</table>";

// Check customer_spaces table structure
echo "<h2>3. customer_spaces Table Structure</h2>";
try {
    $stmt = $db->query("DESCRIBE customer_spaces");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($columns) > 0) {
        echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Could not describe table: " . htmlspecialchars($e->getMessage()) . "</p>";

    // If table doesn't exist, show the SQL to create it
    echo "<h3>SQL to create customer_spaces table:</h3>";
    echo "<pre>";
    echo htmlspecialchars("
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
  `amenities` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `price_per_hour` decimal(10,2) NOT NULL,
  `price_per_day` decimal(10,2) DEFAULT NULL,
  `min_booking_hours` int(11) DEFAULT 1,
  `max_booking_days` int(11) DEFAULT 30,
  `photos` text DEFAULT NULL,
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
  KEY `idx_postcode` (`postcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
    echo "</pre>";
}

// Check users table for foreign key compatibility
echo "<h2>4. Users Table Check</h2>";
try {
    $stmt = $db->query("SELECT user_id, full_name, email, role FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "<p class='success'>Users table accessible. Sample users:</p>";
        echo "<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['user_id']}</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>{$user['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>No users found in database!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error accessing users table: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test insert capability
echo "<h2>5. Test Insert (Dry Run)</h2>";
try {
    // Check if we can prepare an insert statement
    $stmt = $db->prepare("
        INSERT INTO customer_spaces (
            owner_id, space_name, space_type, address_line1, address_line2,
            city, postcode, latitude, longitude, description, amenities,
            instructions, price_per_hour, price_per_day, min_booking_hours,
            max_booking_days, photos, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    echo "<p class='success'>INSERT statement prepared successfully. The table structure is compatible.</p>";

    // Get a valid user_id for testing
    $userStmt = $db->query("SELECT user_id FROM users WHERE role = 'customer' LIMIT 1");
    $testUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($testUser) {
        echo "<p class='success'>Found test customer user (ID: {$testUser['user_id']}) for foreign key test.</p>";
    } else {
        echo "<p class='warning'>No customer role user found. Creating a listing requires a valid user account.</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>INSERT statement failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Session check
echo "<h2>6. Session Status</h2>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<p class='success'>User is logged in. Session user_id: {$_SESSION['user_id']}</p>";

    // Verify this user exists
    $stmt = $db->prepare("SELECT user_id, full_name, role FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $sessionUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sessionUser) {
        echo "<p class='success'>Session user verified: {$sessionUser['full_name']} ({$sessionUser['role']})</p>";
    } else {
        echo "<p class='error'>Session user_id {$_SESSION['user_id']} does not exist in database!</p>";
    }
} else {
    echo "<p class='warning'>No user logged in. You must be authenticated to create listings.</p>";
    echo "<p>Please <a href='index.html'>log in</a> first, then retry creating a listing.</p>";
}

echo "<hr>";
echo "<p><em>Diagnostic completed at " . date('Y-m-d H:i:s') . "</em></p>";
echo "<p><a href='list-your-space.html'>Back to List Your Space</a></p>";
?>
