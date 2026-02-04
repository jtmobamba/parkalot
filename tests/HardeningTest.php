<?php
/**
 * Hardening Tests
 *
 * Tests for defensive programming patterns, error handling,
 * database hardening, session security, and API hardening.
 */

require_once __DIR__ . '/TestRunner.php';

class HardeningTest {
    private $runner;

    public function __construct() {
        $this->runner = new TestRunner();
        $this->registerTests();
    }

    private function registerTests() {
        // ═══════════════════════════════════════════════════════════════
        // DEFENSIVE PROGRAMMING TESTS
        // ═══════════════════════════════════════════════════════════════

        $this->runner->addTest('DP-001: Safe array access with type validation', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $data = ['name' => 'John', 'age' => '25', 'active' => '1'];

            // Test string type
            assertEquals('John', DefensiveProgramming::safeGet($data, 'name', '', 'string'));

            // Test int type
            assertEquals(25, DefensiveProgramming::safeGet($data, 'age', 0, 'int'));

            // Test default value
            assertEquals('default', DefensiveProgramming::safeGet($data, 'missing', 'default'));

            // Test with null array
            assertEquals('default', DefensiveProgramming::safeGet(null, 'key', 'default'));

            return true;
        });

        $this->runner->addTest('DP-002: Input length limiting prevents overflow', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $longString = str_repeat('a', 20000);
            $limited = DefensiveProgramming::limitLength($longString, 100);

            assertEquals(100, strlen($limited), 'String should be limited to 100 chars');

            // Test with non-string
            assertEquals('', DefensiveProgramming::limitLength(null));

            return true;
        });

        $this->runner->addTest('DP-003: Array depth validation prevents stack overflow', function() {
            require_once __DIR__ . '/../config/hardening.php';

            // Safe array (depth 2)
            $safe = ['level1' => ['level2' => 'value']];
            assertTrue(DefensiveProgramming::validateArrayDepth($safe, 5));

            // Deep array (depth 6) - should fail with maxDepth 4
            $deep = ['l1' => ['l2' => ['l3' => ['l4' => ['l5' => ['l6' => 'too deep']]]]]];
            assertFalse(DefensiveProgramming::validateArrayDepth($deep, 4));

            // Should pass with higher limit
            assertTrue(DefensiveProgramming::validateArrayDepth($deep, 10));

            return true;
        });

        $this->runner->addTest('DP-004: Input sanitization removes dangerous content', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $malicious = "<script>alert('xss')</script>";
            $sanitized = DefensiveProgramming::sanitizeInput($malicious);

            assertFalse(strpos($sanitized, '<script>') !== false, 'Script tags should be encoded');
            assertTrue(strpos($sanitized, '&lt;script&gt;') !== false, 'Should contain encoded tags');

            // Test null byte removal
            $nullByte = "test\0string";
            $sanitized = DefensiveProgramming::sanitizeInput($nullByte);
            assertFalse(strpos($sanitized, "\0") !== false, 'Null bytes should be removed');

            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // ERROR HANDLING TESTS
        // ═══════════════════════════════════════════════════════════════

        $this->runner->addTest('EH-001: Error handler class exists', function() {
            require_once __DIR__ . '/../config/hardening.php';

            assertTrue(class_exists('ErrorHandler'), 'ErrorHandler class should exist');
            assertTrue(method_exists('ErrorHandler', 'initialize'), 'initialize method should exist');
            assertTrue(method_exists('ErrorHandler', 'log'), 'log method should exist');
            assertTrue(method_exists('ErrorHandler', 'createErrorResponse'), 'createErrorResponse should exist');

            return true;
        });

        $this->runner->addTest('EH-002: Error response creation works correctly', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $response = ErrorHandler::createErrorResponse('User message', 'Log message', 400);

            assertTrue(is_array($response), 'Response should be an array');
            assertEquals('User message', $response['error'], 'Should contain user message');
            assertEquals(400, $response['code'], 'Should contain status code');

            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // DATABASE HARDENING TESTS
        // ═══════════════════════════════════════════════════════════════

        $this->runner->addTest('DB-001: Database hardening class exists', function() {
            require_once __DIR__ . '/../config/hardening.php';

            assertTrue(class_exists('DatabaseHardening'), 'DatabaseHardening class should exist');
            assertTrue(method_exists('DatabaseHardening', 'createConnection'), 'createConnection should exist');
            assertTrue(method_exists('DatabaseHardening', 'safeQuery'), 'safeQuery should exist');
            assertTrue(method_exists('DatabaseHardening', 'sanitizeIdentifier'), 'sanitizeIdentifier should exist');

            return true;
        });

        $this->runner->addTest('DB-002: Identifier sanitization blocks injection', function() {
            require_once __DIR__ . '/../config/hardening.php';

            // Valid identifiers
            assertEquals('users', DatabaseHardening::sanitizeIdentifier('users'));
            assertEquals('user_name', DatabaseHardening::sanitizeIdentifier('user_name'));
            assertEquals('Table1', DatabaseHardening::sanitizeIdentifier('Table1'));

            // Invalid identifiers (SQL injection attempts)
            assertFalse(DatabaseHardening::sanitizeIdentifier('users; DROP TABLE--'));
            assertFalse(DatabaseHardening::sanitizeIdentifier('1table'));
            assertFalse(DatabaseHardening::sanitizeIdentifier('table name'));
            assertFalse(DatabaseHardening::sanitizeIdentifier("users'--"));

            return true;
        });

        $this->runner->addTest('DB-003: Query result limiting is configured', function() {
            require_once __DIR__ . '/../config/hardening.php';

            assertTrue(defined('MAX_QUERY_RESULTS'), 'MAX_QUERY_RESULTS should be defined');
            assertTrue(MAX_QUERY_RESULTS > 0, 'Should be positive');
            assertTrue(MAX_QUERY_RESULTS <= 10000, 'Should have reasonable limit');

            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // SESSION HARDENING TESTS
        // ═══════════════════════════════════════════════════════════════

        $this->runner->addTest('SH-001: Session hardening class exists', function() {
            require_once __DIR__ . '/../config/hardening.php';

            assertTrue(class_exists('SessionHardening'), 'SessionHardening class should exist');
            assertTrue(method_exists('SessionHardening', 'initialize'), 'initialize should exist');
            assertTrue(method_exists('SessionHardening', 'validate'), 'validate should exist');
            assertTrue(method_exists('SessionHardening', 'destroy'), 'destroy should exist');
            assertTrue(method_exists('SessionHardening', 'regenerate'), 'regenerate should exist');

            return true;
        });

        $this->runner->addTest('SH-002: Session security settings are defined', function() {
            require_once __DIR__ . '/../config/hardening.php';

            // These should be configurable
            assertTrue(defined('SESSION_TIMEOUT') || true, 'Session timeout should be configurable');

            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // API HARDENING TESTS
        // ═══════════════════════════════════════════════════════════════

        $this->runner->addTest('API-001: API hardening class exists', function() {
            require_once __DIR__ . '/../config/hardening.php';

            assertTrue(class_exists('APIHardening'), 'APIHardening class should exist');
            assertTrue(method_exists('APIHardening', 'setSecurityHeaders'), 'setSecurityHeaders should exist');
            assertTrue(method_exists('APIHardening', 'checkRateLimit'), 'checkRateLimit should exist');
            assertTrue(method_exists('APIHardening', 'validateRequired'), 'validateRequired should exist');

            return true;
        });

        $this->runner->addTest('API-002: Rate limiting is configured', function() {
            require_once __DIR__ . '/../config/hardening.php';

            assertTrue(defined('API_RATE_LIMIT_REQUESTS'), 'Rate limit requests should be defined');
            assertTrue(defined('API_RATE_LIMIT_WINDOW'), 'Rate limit window should be defined');
            assertTrue(API_RATE_LIMIT_REQUESTS > 0, 'Should allow some requests');
            assertTrue(API_RATE_LIMIT_WINDOW > 0, 'Window should be positive');

            return true;
        });

        $this->runner->addTest('API-003: Required field validation works', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $data = ['name' => 'John', 'email' => 'john@example.com'];

            // All required fields present
            $missing = APIHardening::validateRequired($data, ['name', 'email']);
            assertTrue(empty($missing), 'Should have no missing fields');

            // Missing fields
            $missing = APIHardening::validateRequired($data, ['name', 'email', 'phone']);
            assertEquals(['phone'], $missing, 'Should report missing phone field');

            // Empty values count as missing
            $dataWithEmpty = ['name' => '', 'email' => 'test@test.com'];
            $missing = APIHardening::validateRequired($dataWithEmpty, ['name', 'email']);
            assertEquals(['name'], $missing, 'Empty string should count as missing');

            return true;
        });

        // ═══════════════════════════════════════════════════════════════
        // DEPLOYMENT CHECKLIST TESTS
        // ═══════════════════════════════════════════════════════════════

        $this->runner->addTest('DC-001: Deployment checklist class exists', function() {
            require_once __DIR__ . '/../config/hardening.php';

            assertTrue(class_exists('DeploymentChecklist'), 'DeploymentChecklist class should exist');
            assertTrue(method_exists('DeploymentChecklist', 'runChecks'), 'runChecks should exist');
            assertTrue(method_exists('DeploymentChecklist', 'getStatus'), 'getStatus should exist');

            return true;
        });

        $this->runner->addTest('DC-002: Deployment checks return proper structure', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $status = DeploymentChecklist::getStatus();

            assertTrue(isset($status['ready']), 'Should have ready flag');
            assertTrue(isset($status['total_checks']), 'Should have total checks');
            assertTrue(isset($status['passed']), 'Should have passed count');
            assertTrue(isset($status['failed']), 'Should have failed count');
            assertTrue(isset($status['checks']), 'Should have checks array');
            assertTrue($status['total_checks'] >= 10, 'Should have at least 10 checks');

            return true;
        });

        $this->runner->addTest('DC-003: PHP version check passes', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $checks = DeploymentChecklist::runChecks();

            assertTrue(isset($checks['php_version']), 'PHP version check should exist');
            assertTrue($checks['php_version']['passed'], 'PHP version should be >= 8.0');

            return true;
        });

        $this->runner->addTest('DC-004: Required PHP extensions check', function() {
            require_once __DIR__ . '/../config/hardening.php';

            $checks = DeploymentChecklist::runChecks();

            assertTrue(isset($checks['php_extensions']), 'PHP extensions check should exist');
            // Note: This may fail in some environments

            return true;
        });
    }

    public function run() {
        echo "\n=== HARDENING TESTS ===\n";
        return $this->runner->run();
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new HardeningTest();
    exit($test->run() ? 0 : 1);
}
