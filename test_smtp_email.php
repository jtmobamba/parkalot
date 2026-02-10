<?php
/**
 * SMTP Email Test Script
 * This script tests your email configuration
 */

echo "========================================\n";
echo "  SMTP Email Configuration Test\n";
echo "========================================\n\n";

// Check if PHPMailer is installed
if (!file_exists(__DIR__ . '/vendor/phpmailer/PHPMailer.php')) {
    echo "❌ ERROR: PHPMailer not found!\n";
    echo "   Expected location: " . __DIR__ . "/vendor/phpmailer/PHPMailer.php\n";
    exit(1);
}
echo "✅ PHPMailer library found\n";

// Check if email config exists
if (!file_exists(__DIR__ . '/config/email_config.php')) {
    echo "❌ ERROR: Email configuration file not found!\n";
    echo "   Expected location: " . __DIR__ . "/config/email_config.php\n";
    exit(1);
}
echo "✅ Email configuration file found\n";

require_once __DIR__ . '/config/email_config.php';

// Check if credentials are configured
echo "\n--- Current Email Configuration ---\n";
echo "SMTP Host: " . EmailConfig::SMTP_HOST . "\n";
echo "SMTP Port: " . EmailConfig::SMTP_PORT . "\n";
echo "SMTP Secure: " . EmailConfig::SMTP_SECURE . "\n";
echo "From Email: " . EmailConfig::FROM_EMAIL . "\n";
echo "From Name: " . EmailConfig::FROM_NAME . "\n";

if (EmailConfig::SMTP_USERNAME === 'your-email@gmail.com' || 
    EmailConfig::SMTP_PASSWORD === 'your-app-password') {
    echo "\n⚠️  WARNING: Email credentials not configured!\n";
    echo "   Please edit: config/email_config.php\n";
    echo "   And set your SMTP username and password.\n\n";
    echo "   Quick Setup Options:\n";
    echo "   1. Gmail: Use App Password from https://myaccount.google.com/apppasswords\n";
    echo "   2. Mailtrap: Sign up at https://mailtrap.io (FREE for testing)\n";
    echo "   3. SendGrid: Get API key from https://sendgrid.com\n\n";
    exit(1);
}

echo "\n--- Testing Database Connection ---\n";
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::connect();
    echo "✅ Database connection successful\n";
    
    // Check if we have test users
    $stmt = $db->prepare("SELECT user_id, email, full_name FROM users LIMIT 1");
    $stmt->execute();
    $testUser = $stmt->fetch();
    
    if (!$testUser) {
        echo "⚠️  No users found in database. Creating a test user...\n";
        
        // Create a test user
        $stmt = $db->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([
            'Test User',
            'test@parkalot.com',
            password_hash('password123', PASSWORD_DEFAULT)
        ]);
        $testUserId = $db->lastInsertId();
        
        $testUser = [
            'user_id' => $testUserId,
            'email' => 'test@parkalot.com',
            'full_name' => 'Test User'
        ];
        echo "✅ Test user created (ID: $testUserId)\n";
    }
    
    echo "Test user: {$testUser['full_name']} ({$testUser['email']})\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Testing OTP Generation ---\n";
require_once __DIR__ . '/app/services/EmailVerificationService.php';

$emailService = new EmailVerificationService($db);

echo "Generating OTP for user ID {$testUser['user_id']}...\n";
$result = $emailService->generateAndSendOTP($testUser['user_id'], $testUser['email']);

echo "\n========================================\n";
echo "  TEST RESULTS\n";
echo "========================================\n\n";

if (isset($result['success']) && $result['success'] === true) {
    echo "✅ SUCCESS! Email sent successfully!\n\n";
    echo "Message: {$result['message']}\n";
    echo "Expires in: {$result['expires_in']}\n\n";
    
    // Check database for OTP
    $stmt = $db->prepare("SELECT otp_code, expires_at FROM email_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$testUser['user_id']]);
    $otp = $stmt->fetch();
    
    if ($otp) {
        echo "📧 OTP Code: {$otp['otp_code']}\n";
        echo "⏰ Expires at: {$otp['expires_at']}\n\n";
        echo "Check your email inbox!\n";
        echo "Email sent to: {$testUser['email']}\n";
    }
    
} else {
    echo "❌ FAILED: " . ($result['error'] ?? 'Unknown error') . "\n\n";
    
    echo "Troubleshooting:\n";
    echo "1. Check your SMTP credentials in config/email_config.php\n";
    echo "2. For Gmail: Use App Password, not regular password\n";
    echo "3. Check firewall settings (port 587 should be open)\n";
    echo "4. Check PHP error logs: C:\\xampp\\php\\logs\\php_error_log\n";
    echo "5. Try enabling DEBUG mode in email_config.php\n\n";
    
    echo "Common SMTP Ports:\n";
    echo "- Port 587: TLS (recommended)\n";
    echo "- Port 465: SSL\n";
    echo "- Port 25: Plain (not recommended)\n\n";
}

echo "\n========================================\n";
echo "For detailed setup instructions, see:\n";
echo "SMTP_SETUP_GUIDE.txt\n";
echo "========================================\n";
?>
