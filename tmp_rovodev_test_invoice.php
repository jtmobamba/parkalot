<?php
/**
 * Test Script to Debug Invoice Loading Issue
 */

echo "🔍 Invoice System Debug Test\n";
echo str_repeat("=", 60) . "\n\n";

// Start session to check if user is logged in
session_start();

echo "1. Checking Session:\n";
echo "   User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "   Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "   User Name: " . ($_SESSION['user_name'] ?? 'NOT SET') . "\n\n";

if (!isset($_SESSION['user_id'])) {
    echo "❌ No user logged in! Please login first.\n";
    echo "   Go to: http://localhost/parkalot_system/public/index.html\n\n";
    exit;
}

$userId = $_SESSION['user_id'];

// Load database
require_once 'config/database.php';
$db = Database::connect();

echo "2. Checking Database Connection:\n";
echo "   ✅ Connected to database\n\n";

// Check if reservations table exists
echo "3. Checking Reservations Table:\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'reservations'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ Reservations table exists\n\n";
    } else {
        echo "   ❌ Reservations table NOT FOUND!\n\n";
        exit;
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
    exit;
}

// Check table structure
echo "4. Checking Table Structure:\n";
try {
    $stmt = $db->query("DESCRIBE reservations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "   - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Count total reservations
echo "5. Counting All Reservations:\n";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM reservations");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Total reservations in database: {$result['total']}\n\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Count user's reservations
echo "6. Counting User's Reservations (user_id={$userId}):\n";
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Your reservations: {$result['total']}\n\n";
    
    if ($result['total'] == 0) {
        echo "   ⚠️  You have NO reservations yet!\n";
        echo "   Create a reservation first:\n";
        echo "   1. Go to customer dashboard\n";
        echo "   2. Fill in reservation form\n";
        echo "   3. Submit reservation\n\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Get user's reservation details
echo "7. Your Reservation Details:\n";
try {
    $stmt = $db->prepare("
        SELECT r.*, g.garage_name, g.price_per_hour
        FROM reservations r
        LEFT JOIN garages g ON g.garage_id = r.garage_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$userId]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($reservations)) {
        echo "   No reservations found.\n\n";
    } else {
        foreach ($reservations as $i => $res) {
            echo "   Reservation #" . ($i + 1) . ":\n";
            echo "   - ID: {$res['reservation_id']}\n";
            echo "   - Garage: {$res['garage_name']} (ID: {$res['garage_id']})\n";
            echo "   - Start: {$res['start_time']}\n";
            echo "   - End: {$res['end_time']}\n";
            echo "   - Price: £" . ($res['price'] ?? 'NOT SET') . "\n";
            echo "   - Status: {$res['status']}\n";
            echo "   - Price per hour: £" . ($res['price_per_hour'] ?? 'NOT SET') . "\n";
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Test the InvoiceController
echo "8. Testing InvoiceController:\n";
try {
    require_once 'app/controllers/InvoiceController.php';
    $controller = new InvoiceController($db);
    $invoice = $controller->getUserInvoice($userId);
    
    echo "   ✅ InvoiceController loaded successfully\n";
    echo "   Invoice data:\n";
    echo "   - Count: {$invoice['count']}\n";
    echo "   - Total: £{$invoice['total']}\n";
    echo "   - Reservations: " . count($invoice['reservations']) . "\n\n";
    
    if (!empty($invoice['reservations'])) {
        echo "   First reservation in invoice:\n";
        $first = $invoice['reservations'][0];
        echo "   - ID: {$first['reservation_id']}\n";
        echo "   - Garage: {$first['garage_name']}\n";
        echo "   - Price: £{$first['price']}\n\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

// Test API endpoint
echo "9. Testing API Endpoint:\n";
echo "   Simulating API call to /api/index.php?route=invoice\n";
try {
    require_once 'app/controllers/InvoiceController.php';
    $controller = new InvoiceController($db);
    $result = $controller->getUserInvoice($userId);
    
    echo "   API Response:\n";
    echo "   " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "✅ Debug Test Complete!\n\n";

echo "Next Steps:\n";
if (!isset($_SESSION['user_id'])) {
    echo "1. Login to the system first\n";
} else {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($count == 0) {
        echo "1. You have no reservations - create one first!\n";
        echo "2. Go to: http://localhost/parkalot_system/public/customer_dashboard.html\n";
        echo "3. Fill in the reservation form and submit\n";
        echo "4. Then view invoice page\n";
    } else {
        echo "1. Check browser console (F12) when viewing invoice.html\n";
        echo "2. Look for console.log messages\n";
        echo "3. Check for any JavaScript errors\n";
        echo "4. Verify the API endpoint is being called\n";
    }
}

echo "\n";
?>
