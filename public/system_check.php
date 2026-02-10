<?php
/**
 * System Health & Security Check
 * Access at: http://localhost:8080/system_check.php
 */

// Load configurations
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/hardening.php';
require_once __DIR__ . '/../config/compliance.php';

// Initialize
ErrorHandler::initialize();
header('Content-Type: text/html; charset=utf-8');

$results = [];

// ═══════════════════════════════════════════════════════════════════
// 1. DEFENSIVE PROGRAMMING TESTS
// ═══════════════════════════════════════════════════════════════════

$results['Defensive Programming'] = [];

// Test safe array access
$testArray = ['name' => 'John', 'age' => '25'];
$value = DefensiveProgramming::safeGet($testArray, 'name', '', 'string');
$results['Defensive Programming']['Safe Array Access'] = ($value === 'John');

// Test input sanitization
$malicious = '<script>alert("xss")</script>';
$sanitized = DefensiveProgramming::sanitizeInput($malicious);
$results['Defensive Programming']['XSS Sanitization'] = (strpos($sanitized, '<script>') === false);

// Test input length limiting
$longString = str_repeat('a', 20000);
$limited = DefensiveProgramming::limitLength($longString, 100);
$results['Defensive Programming']['Input Length Limiting'] = (strlen($limited) === 100);

// ═══════════════════════════════════════════════════════════════════
// 2. ERROR HANDLING TESTS
// ═══════════════════════════════════════════════════════════════════

$results['Error Handling'] = [];

// Test error handler exists
$results['Error Handling']['Handler Initialized'] = class_exists('ErrorHandler');

// Test logging capability
$logDir = __DIR__ . '/../logs';
$results['Error Handling']['Log Directory Writable'] = is_dir($logDir) && is_writable($logDir);

// ═══════════════════════════════════════════════════════════════════
// 3. DATABASE HARDENING TESTS
// ═══════════════════════════════════════════════════════════════════

$results['Database Hardening'] = [];

// Test identifier sanitization
$safeId = DatabaseHardening::sanitizeIdentifier('users');
$unsafeId = DatabaseHardening::sanitizeIdentifier('users; DROP TABLE--');
$results['Database Hardening']['Identifier Sanitization'] = ($safeId === 'users' && $unsafeId === false);

// Test database connection
try {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::connect();
    $results['Database Hardening']['Connection Active'] = ($db instanceof PDO);
} catch (Exception $e) {
    $results['Database Hardening']['Connection Active'] = false;
}

// ═══════════════════════════════════════════════════════════════════
// 4. SESSION SECURITY TESTS
// ═══════════════════════════════════════════════════════════════════

$results['Session Security'] = [];

// Test session configuration
$results['Session Security']['Cookie HttpOnly'] = (ini_get('session.cookie_httponly') == 1);
$results['Session Security']['Strict Mode'] = (ini_get('session.use_strict_mode') == 1);
$results['Session Security']['Timeout Configured'] = defined('SESSION_TIMEOUT');

// ═══════════════════════════════════════════════════════════════════
// 5. API HARDENING TESTS
// ═══════════════════════════════════════════════════════════════════

$results['API Hardening'] = [];

// Test rate limiting configuration
$results['API Hardening']['Rate Limiting Configured'] = defined('API_RATE_LIMIT_REQUESTS');

// Test required field validation
$data = ['name' => 'John'];
$missing = APIHardening::validateRequired($data, ['name', 'email']);
$results['API Hardening']['Field Validation Works'] = (count($missing) === 1 && $missing[0] === 'email');

// ═══════════════════════════════════════════════════════════════════
// 6. COMPLIANCE TESTS
// ═══════════════════════════════════════════════════════════════════

$results['GDPR Compliance'] = [];
$results['PCI DSS Compliance'] = [];

// GDPR
$results['GDPR Compliance']['Data Minimization'] = defined('GDPR_REQUIRED_USER_FIELDS');
$results['GDPR Compliance']['Retention Configured'] = defined('GDPR_SESSION_DATA_RETENTION');

$testData = ['full_name' => 'Test', 'email' => 'test@test.com'];
$exported = GDPRCompliance::exportUserData($testData);
$results['GDPR Compliance']['Data Export Works'] = (strpos($exported, 'Test') !== false);

// PCI DSS
$results['PCI DSS Compliance']['Card Storage Disabled'] = (PCI_STORE_CARD_DATA === false);
$results['PCI DSS Compliance']['CVV Storage Disabled'] = (PCI_STORE_CVV === false);

$masked = PCIDSSCompliance::maskCardNumber('4111111111111111');
$results['PCI DSS Compliance']['Card Masking Works'] = ($masked === '****-****-****-1111');

// ═══════════════════════════════════════════════════════════════════
// 7. DEPLOYMENT CHECKLIST
// ═══════════════════════════════════════════════════════════════════

$deploymentStatus = DeploymentChecklist::getStatus();

// ═══════════════════════════════════════════════════════════════════
// RENDER RESULTS
// ═══════════════════════════════════════════════════════════════════

$totalPassed = 0;
$totalFailed = 0;

foreach ($results as $category => $tests) {
    foreach ($tests as $passed) {
        if ($passed) $totalPassed++;
        else $totalFailed++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkaLot System Check</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 10px; }
        .summary { background: <?= $totalFailed === 0 ? '#4CAF50' : '#ff9800' ?>; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .summary h2 { font-size: 24px; }
        .category { background: white; border-radius: 8px; margin-bottom: 15px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .category-header { background: #333; color: white; padding: 15px 20px; font-weight: bold; }
        .test { display: flex; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid #eee; }
        .test:last-child { border-bottom: none; }
        .pass { color: #4CAF50; font-weight: bold; }
        .fail { color: #f44336; font-weight: bold; }
        .deployment { background: white; border-radius: 8px; padding: 20px; margin-top: 20px; }
        .deployment h3 { margin-bottom: 15px; }
        .check-item { padding: 8px 0; border-bottom: 1px solid #eee; }
        .timestamp { color: #666; font-size: 14px; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 ParkaLot System Security Check</h1>
        <p style="color: #666; margin-bottom: 20px;">Production hardening verification</p>

        <div class="summary">
            <h2><?= $totalFailed === 0 ? '✅ All Checks Passed' : "⚠️ {$totalFailed} Check(s) Need Attention" ?></h2>
            <p><?= $totalPassed ?> passed, <?= $totalFailed ?> failed</p>
        </div>

        <?php foreach ($results as $category => $tests): ?>
        <div class="category">
            <div class="category-header"><?= htmlspecialchars($category) ?></div>
            <?php foreach ($tests as $test => $passed): ?>
            <div class="test">
                <span><?= htmlspecialchars($test) ?></span>
                <span class="<?= $passed ? 'pass' : 'fail' ?>"><?= $passed ? '✓ PASS' : '✗ FAIL' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div class="deployment">
            <h3>📋 Deployment Readiness</h3>
            <p style="margin-bottom: 15px;">
                Status: <strong><?= $deploymentStatus['ready'] ? '✅ Ready' : '⚠️ Not Ready' ?></strong>
                (<?= $deploymentStatus['passed'] ?>/<?= $deploymentStatus['total_checks'] ?> checks passed)
            </p>
            <?php foreach ($deploymentStatus['checks'] as $name => $check): ?>
            <div class="check-item">
                <span class="<?= $check['passed'] ? 'pass' : 'fail' ?>">
                    <?= $check['passed'] ? '✓' : '✗' ?>
                </span>
                <?= htmlspecialchars($check['name']) ?>
                <?php if (isset($check['details'])): ?>
                    <span style="color: #666; font-size: 12px;">(<?= htmlspecialchars($check['details']) ?>)</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <p class="timestamp">
            Checked at: <?= date('Y-m-d H:i:s') ?> |
            PHP <?= PHP_VERSION ?> |
            Environment: <?= getenv('APP_ENV') ?: 'development' ?>
        </p>
    </div>
</body>
</html>
