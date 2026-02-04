<?php
/**
 * Authentication Tests
 *
 * Tests for user authentication, validation, and security features.
 * These tests verify the core authentication logic without requiring
 * a live database connection.
 */

require_once __DIR__ . '/TestRunner.php';

class AuthenticationTest {
    private $runner;

    public function __construct() {
        $this->runner = new TestRunner();
        $this->registerTests();
    }

    private function registerTests() {
        // Test 1: Email Validation
        $this->runner->addTest('Email validation accepts valid emails', function() {
            $validEmails = [
                'user@example.com',
                'test.user@domain.co.uk',
                'name+tag@gmail.com'
            ];

            foreach ($validEmails as $email) {
                assertTrue(
                    filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                    "Email '{$email}' should be valid"
                );
            }
            return true;
        });

        // Test 2: Email Validation Rejects Invalid
        $this->runner->addTest('Email validation rejects invalid emails', function() {
            $invalidEmails = [
                'notanemail',
                '@nodomain.com',
                'spaces in@email.com',
                ''
            ];

            foreach ($invalidEmails as $email) {
                assertFalse(
                    filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                    "Email '{$email}' should be invalid"
                );
            }
            return true;
        });

        // Test 3: Password Strength Validation
        $this->runner->addTest('Password validation enforces security requirements', function() {
            $validatePassword = function($password) {
                if (strlen($password) < 8) return false;
                if (!preg_match('/[A-Z]/', $password)) return false;
                if (!preg_match('/[a-z]/', $password)) return false;
                if (!preg_match('/[0-9]/', $password)) return false;
                return true;
            };

            // Valid passwords
            assertTrue($validatePassword('Password123'), 'Password123 should be valid');
            assertTrue($validatePassword('SecurePass1'), 'SecurePass1 should be valid');

            // Invalid passwords
            assertFalse($validatePassword('short1A'), 'Too short password should fail');
            assertFalse($validatePassword('nouppercase1'), 'No uppercase should fail');
            assertFalse($validatePassword('NOLOWERCASE1'), 'No lowercase should fail');
            assertFalse($validatePassword('NoNumbers'), 'No numbers should fail');

            return true;
        });

        // Test 4: Password Hashing
        $this->runner->addTest('Password hashing works correctly', function() {
            $password = 'TestPassword123';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Hash should be different from password
            assertTrue($hash !== $password, 'Hash should differ from password');

            // Verification should work
            assertTrue(password_verify($password, $hash), 'Password verification should succeed');

            // Wrong password should fail
            assertFalse(password_verify('WrongPassword', $hash), 'Wrong password should fail');

            return true;
        });

        // Test 5: Session Token Generation
        $this->runner->addTest('CSRF token generation produces unique tokens', function() {
            $generateToken = function() {
                return bin2hex(random_bytes(32));
            };

            $token1 = $generateToken();
            $token2 = $generateToken();

            // Tokens should be 64 characters (32 bytes = 64 hex chars)
            assertEquals(64, strlen($token1), 'Token should be 64 characters');

            // Tokens should be unique
            assertTrue($token1 !== $token2, 'Tokens should be unique');

            return true;
        });

        // Test 6: OTP Generation
        $this->runner->addTest('OTP generation produces valid 6-digit codes', function() {
            $generateOTP = function() {
                return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            };

            for ($i = 0; $i < 10; $i++) {
                $otp = $generateOTP();
                assertEquals(6, strlen($otp), 'OTP should be 6 digits');
                assertTrue(ctype_digit($otp), 'OTP should contain only digits');
                assertTrue((int)$otp >= 100000 && (int)$otp <= 999999, 'OTP should be in valid range');
            }

            return true;
        });

        // Test 7: Input Sanitization
        $this->runner->addTest('Input sanitization removes dangerous content', function() {
            $sanitize = function($input) {
                return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
            };

            // XSS attack prevention
            $malicious = '<script>alert("xss")</script>';
            $sanitized = $sanitize($malicious);
            assertFalse(strpos($sanitized, '<script>') !== false, 'Script tags should be removed');

            // HTML injection prevention
            $htmlInput = '<div onclick="evil()">Click me</div>';
            $sanitized = $sanitize($htmlInput);
            assertFalse(strpos($sanitized, '<div') !== false, 'HTML tags should be escaped');

            // Normal input should pass through
            $normal = 'John Doe';
            assertEquals('John Doe', $sanitize($normal), 'Normal input should be unchanged');

            return true;
        });

        // Test 8: Rate Limiting Logic
        $this->runner->addTest('Rate limiting logic correctly identifies exceeded limits', function() {
            $checkRateLimit = function($attempts, $maxAttempts, $timeWindow) {
                return $attempts >= $maxAttempts;
            };

            // Under limit
            assertFalse($checkRateLimit(3, 5, 900), '3 attempts should be under limit of 5');

            // At limit
            assertTrue($checkRateLimit(5, 5, 900), '5 attempts should trigger rate limit');

            // Over limit
            assertTrue($checkRateLimit(10, 5, 900), '10 attempts should trigger rate limit');

            return true;
        });
    }

    public function run() {
        echo "\n=== AUTHENTICATION TESTS ===\n";
        return $this->runner->run();
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new AuthenticationTest();
    exit($test->run() ? 0 : 1);
}
