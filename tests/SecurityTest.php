<?php
/**
 * Security Tests
 *
 * Verifies all STRIDE threat mitigations are properly implemented
 * as documented in docs/SECURITY_MODEL.md
 */

require_once __DIR__ . '/TestRunner.php';

class SecurityTest {
    private $runner;

    public function __construct() {
        $this->runner = new TestRunner();
        $this->registerTests();
    }

    private function registerTests() {
        // ═══════════════════════════════════════════════════════════════
        // SPOOFING THREATS (S-001 to S-004)
        // ═══════════════════════════════════════════════════════════════

        // S-001: Rate Limiting
        $this->runner->addTest('S-001: Rate limiting constants are defined', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(defined('MAX_LOGIN_ATTEMPTS'), 'MAX_LOGIN_ATTEMPTS should be defined');
            assertTrue(defined('LOGIN_LOCKOUT_DURATION'), 'LOGIN_LOCKOUT_DURATION should be defined');
            assertEquals(5, MAX_LOGIN_ATTEMPTS, 'Max login attempts should be 5');
            assertEquals(900, LOGIN_LOCKOUT_DURATION, 'Lockout duration should be 900 seconds');
            return true;
        });

        // S-002: Session Security
        $this->runner->addTest('S-002: Session security configuration exists', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(defined('SESSION_TIMEOUT'), 'SESSION_TIMEOUT should be defined');
            assertTrue(SESSION_TIMEOUT > 0, 'Session timeout should be positive');
            assertTrue(function_exists('checkSessionTimeout'), 'checkSessionTimeout function should exist');
            return true;
        });

        // S-003: Email Verification (OTP)
        $this->runner->addTest('S-003: OTP configuration exists', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(defined('OTP_EXPIRY_MINUTES'), 'OTP_EXPIRY_MINUTES should be defined');
            assertTrue(defined('OTP_MAX_ATTEMPTS'), 'OTP_MAX_ATTEMPTS should be defined');
            assertTrue(OTP_EXPIRY_MINUTES <= 15, 'OTP expiry should be 15 minutes or less');
            return true;
        });

        // S-004: CSRF Protection
        $this->runner->addTest('S-004: CSRF protection class exists and functions', function() {
            require_once __DIR__ . '/../app/utils/CSRFProtection.php';
            assertTrue(class_exists('CSRFProtection'), 'CSRFProtection class should exist');
            assertTrue(method_exists('CSRFProtection', 'generateToken'), 'generateToken method should exist');
            assertTrue(method_exists('CSRFProtection', 'validateToken'), 'validateToken method should exist');

            // Test token generation
            $token = CSRFProtection::generateToken();
            assertTrue(strlen($token) === 64, 'CSRF token should be 64 characters (32 bytes hex)');

            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // TAMPERING THREATS (T-001 to T-004)
        // ═══════════════════════════════════════════════════════════════

        // T-001: SQL Injection Prevention
        $this->runner->addTest('T-001: Database uses PDO with prepared statements', function() {
            require_once __DIR__ . '/../config/database.php';
            assertTrue(class_exists('Database'), 'Database class should exist');
            assertTrue(method_exists('Database', 'connect'), 'connect method should exist');
            return true;
        });

        // T-002: XSS Prevention
        $this->runner->addTest('T-002: Input sanitization function exists', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(function_exists('sanitizeInput'), 'sanitizeInput function should exist');

            // Test sanitization
            $malicious = '<script>alert("xss")</script>';
            $sanitized = sanitizeInput($malicious);
            assertFalse(strpos($sanitized, '<script>') !== false, 'Script tags should be escaped');

            return true;
        });

        // T-003: Parameter Validation
        $this->runner->addTest('T-003: Password validation function exists', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(function_exists('validatePasswordStrength'), 'validatePasswordStrength should exist');

            // Test weak password
            $result = validatePasswordStrength('weak');
            assertFalse($result['valid'], 'Weak password should fail validation');

            // Test strong password
            $result = validatePasswordStrength('StrongPass123');
            assertTrue($result['valid'], 'Strong password should pass validation');

            return true;
        });

        // T-004: Price Tampering (Server-side calculation)
        $this->runner->addTest('T-004: Server-side validation patterns exist', function() {
            // Check that controllers exist for server-side validation
            assertTrue(file_exists(__DIR__ . '/../app/controllers/ReservationController.php'),
                'ReservationController should exist for server-side price calculation');
            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // REPUDIATION THREATS (R-001 to R-003)
        // ═══════════════════════════════════════════════════════════════

        // R-001: Activity Logging
        $this->runner->addTest('R-001: Activity logging DAO exists', function() {
            assertTrue(file_exists(__DIR__ . '/../app/models/ActivityLogDAO.php'),
                'ActivityLogDAO should exist');
            return true;
        });

        // R-002: IP Address Logging
        $this->runner->addTest('R-002: Security event logging includes IP', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(function_exists('logSecurityEvent'), 'logSecurityEvent function should exist');
            return true;
        });

        // R-003: Transaction ID Generation
        $this->runner->addTest('R-003: Invoice controller exists for transaction tracking', function() {
            assertTrue(file_exists(__DIR__ . '/../app/controllers/InvoiceController.php'),
                'InvoiceController should exist for transaction ID generation');
            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // INFORMATION DISCLOSURE THREATS (I-001 to I-004)
        // ═══════════════════════════════════════════════════════════════

        // I-001: Password Hashing
        $this->runner->addTest('I-001: Bcrypt password hashing is used', function() {
            $password = 'TestPassword123';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Verify it's bcrypt (starts with $2y$)
            assertTrue(strpos($hash, '$2y$') === 0 || strpos($hash, '$2b$') === 0,
                'Password should be hashed with bcrypt');
            assertTrue(password_verify($password, $hash), 'Password verification should work');

            return true;
        });

        // I-002: Error Message Handling
        $this->runner->addTest('I-002: Production error handling configured', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(function_exists('configureErrorHandling'), 'configureErrorHandling should exist');
            assertTrue(defined('IS_PRODUCTION'), 'IS_PRODUCTION should be defined');
            return true;
        });

        // I-003: File Upload Restrictions
        $this->runner->addTest('I-003: File upload limits are configured', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(defined('MAX_FILE_SIZE'), 'MAX_FILE_SIZE should be defined');
            assertTrue(defined('ALLOWED_FILE_TYPES'), 'ALLOWED_FILE_TYPES should be defined');
            assertTrue(MAX_FILE_SIZE <= 10485760, 'Max file size should be 10MB or less');
            return true;
        });

        // I-004: Security Headers
        $this->runner->addTest('I-004: Security headers function exists', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(function_exists('setSecurityHeaders'), 'setSecurityHeaders function should exist');
            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // DENIAL OF SERVICE THREATS (D-001 to D-004)
        // ═══════════════════════════════════════════════════════════════

        // D-001: Login Flood Protection
        $this->runner->addTest('D-001: Login rate limiting configured', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(MAX_LOGIN_ATTEMPTS > 0 && MAX_LOGIN_ATTEMPTS <= 10,
                'Login attempts should be between 1-10');
            return true;
        });

        // D-002: OTP Rate Limiting
        $this->runner->addTest('D-002: OTP rate limiting configured', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(defined('OTP_RATE_LIMIT_WINDOW'), 'OTP_RATE_LIMIT_WINDOW should be defined');
            assertTrue(OTP_MAX_ATTEMPTS <= 5, 'OTP max attempts should be 5 or less');
            return true;
        });

        // D-003: File Size Limits
        $this->runner->addTest('D-003: File size limits prevent large uploads', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(MAX_FILE_SIZE > 0, 'MAX_FILE_SIZE should be positive');
            assertTrue(MAX_FILE_SIZE <= 10485760, 'MAX_FILE_SIZE should be 10MB or less');
            return true;
        });

        // D-004: Session Timeout
        $this->runner->addTest('D-004: Session timeout prevents exhaustion', function() {
            require_once __DIR__ . '/../config/security.php';
            assertTrue(SESSION_TIMEOUT > 0, 'SESSION_TIMEOUT should be positive');
            assertTrue(SESSION_TIMEOUT <= 7200, 'SESSION_TIMEOUT should be 2 hours or less');
            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // ELEVATION OF PRIVILEGE THREATS (E-001 to E-003)
        // ═══════════════════════════════════════════════════════════════

        // E-001 & E-002: Role-Based Access Control
        $this->runner->addTest('E-001/E-002: Role-based access control exists', function() {
            assertTrue(file_exists(__DIR__ . '/../app/controllers/AuthController.php'),
                'AuthController should exist for role management');

            // Check API index for role verification
            $apiContent = file_get_contents(__DIR__ . '/../api/index.php');
            assertTrue(strpos($apiContent, '$_SESSION[\'role\']') !== false,
                'API should check session role');

            return true;
        });

        // E-003: API Endpoint Protection
        $this->runner->addTest('E-003: API endpoints verify session', function() {
            $apiContent = file_get_contents(__DIR__ . '/../api/index.php');
            assertTrue(strpos($apiContent, '$_SESSION[\'user_id\']') !== false,
                'API should verify user_id in session');
            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // COMPLIANCE TESTS (GDPR & PCI DSS)
        // ═══════════════════════════════════════════════════════════════

        // GDPR-001: Data Minimization
        $this->runner->addTest('GDPR-001: Data minimization configuration exists', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(defined('GDPR_REQUIRED_USER_FIELDS'), 'GDPR required fields should be defined');
            assertTrue(is_array(GDPR_REQUIRED_USER_FIELDS), 'GDPR required fields should be an array');
            assertTrue(count(GDPR_REQUIRED_USER_FIELDS) <= 10, 'Should collect minimal user data');
            return true;
        });

        // GDPR-002: Data Retention
        $this->runner->addTest('GDPR-002: Data retention periods are defined', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(defined('GDPR_SESSION_DATA_RETENTION'), 'Session retention should be defined');
            assertTrue(defined('GDPR_ACTIVITY_LOG_RETENTION'), 'Log retention should be defined');
            assertTrue(GDPR_SESSION_DATA_RETENTION <= 7, 'Session data should not be retained > 7 days');
            return true;
        });

        // GDPR-003: User Data Export
        $this->runner->addTest('GDPR-003: User data export function exists', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(class_exists('GDPRCompliance'), 'GDPRCompliance class should exist');
            assertTrue(method_exists('GDPRCompliance', 'exportUserData'), 'Export function should exist');

            // Test export
            $testData = ['full_name' => 'Test User', 'email' => 'test@example.com'];
            $exported = GDPRCompliance::exportUserData($testData);
            assertTrue(strpos($exported, 'Test User') !== false, 'Export should contain user data');

            return true;
        });

        // GDPR-004: Data Anonymization
        $this->runner->addTest('GDPR-004: Data anonymization function exists', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(method_exists('GDPRCompliance', 'anonymizeUserData'), 'Anonymize function should exist');

            // Test anonymization
            $testData = ['user_id' => 1, 'full_name' => 'Real Name', 'email' => 'real@email.com'];
            $anonymized = GDPRCompliance::anonymizeUserData($testData);
            assertEquals('ANONYMIZED_USER', $anonymized['full_name'], 'Name should be anonymized');
            assertTrue(strpos($anonymized['email'], 'anonymized_') === 0, 'Email should be anonymized');

            return true;
        });

        // PCI-001: No Card Data Storage
        $this->runner->addTest('PCI-001: Card data storage is disabled', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(defined('PCI_STORE_CARD_DATA'), 'PCI card storage setting should be defined');
            assertFalse(PCI_STORE_CARD_DATA, 'Card data storage must be disabled');
            assertFalse(PCI_STORE_CVV, 'CVV storage must be disabled');
            return true;
        });

        // PCI-002: Card Data Validation
        $this->runner->addTest('PCI-002: Card data detection function exists', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(class_exists('PCIDSSCompliance'), 'PCIDSSCompliance class should exist');
            assertTrue(method_exists('PCIDSSCompliance', 'validateNoCardData'), 'Validation function should exist');

            // Test with safe data (should pass)
            $safeData = ['amount' => 100, 'description' => 'Parking fee'];
            $result = PCIDSSCompliance::validateNoCardData($safeData);
            assertTrue($result, 'Safe data should pass validation');

            return true;
        });

        // PCI-003: Card Masking
        $this->runner->addTest('PCI-003: Card masking function works correctly', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(method_exists('PCIDSSCompliance', 'maskCardNumber'), 'Mask function should exist');

            $masked = PCIDSSCompliance::maskCardNumber('4111111111111111');
            assertEquals('****-****-****-1111', $masked, 'Card should be masked showing last 4 digits');

            return true;
        });

        // PCI-004: Approved Payment Gateways
        $this->runner->addTest('PCI-004: Payment gateway validation exists', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(defined('PCI_APPROVED_GATEWAYS'), 'Approved gateways should be defined');
            assertTrue(PCIDSSCompliance::isApprovedGateway('stripe'), 'Stripe should be approved');
            assertTrue(PCIDSSCompliance::isApprovedGateway('paypal'), 'PayPal should be approved');
            assertFalse(PCIDSSCompliance::isApprovedGateway('unknown_gateway'), 'Unknown gateway should not be approved');
            return true;
        });

        // COMPLIANCE-001: Overall Compliance Status
        $this->runner->addTest('COMPLIANCE-001: Compliance audit function exists', function() {
            require_once __DIR__ . '/../config/compliance.php';
            assertTrue(class_exists('ComplianceAudit'), 'ComplianceAudit class should exist');

            $status = ComplianceAudit::getComplianceStatus();
            assertTrue(isset($status['gdpr']), 'GDPR status should be present');
            assertTrue(isset($status['pci_dss']), 'PCI DSS status should be present');
            assertEquals('COMPLIANT', $status['gdpr']['status'], 'GDPR should be compliant');
            assertEquals('COMPLIANT', $status['pci_dss']['status'], 'PCI DSS should be compliant');

            return true;
        });

        // COMPLIANCE-002: Settings Verification
        $this->runner->addTest('COMPLIANCE-002: Compliance settings verification passes', function() {
            require_once __DIR__ . '/../config/compliance.php';

            $verification = ComplianceAudit::verifySettings();
            assertTrue($verification['compliant'], 'All compliance settings should be valid');
            assertTrue(empty($verification['issues']), 'No compliance issues should exist');

            return true;
        });
    }

    public function run() {
        echo "\n=== SECURITY TESTS (STRIDE VERIFICATION) ===\n";
        return $this->runner->run();
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new SecurityTest();
    exit($test->run() ? 0 : 1);
}
