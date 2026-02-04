<?php
/**
 * Security Configuration
 * Centralized security settings for the ParkaLot system
 */

// Session timeout (in seconds)
define('SESSION_TIMEOUT', 3600); // 1 hour

// Login rate limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_DURATION', 900); // 15 minutes in seconds

// OTP settings
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_MAX_ATTEMPTS', 3);
define('OTP_RATE_LIMIT_WINDOW', 300); // 5 minutes in seconds

// Password policy
define('MIN_PASSWORD_LENGTH', 8);
define('REQUIRE_UPPERCASE', true);
define('REQUIRE_LOWERCASE', true);
define('REQUIRE_NUMBERS', true);
define('REQUIRE_SPECIAL_CHARS', false);

// File upload limits
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx']);

// Environment detection
define('IS_PRODUCTION', getenv('APP_ENV') === 'production');

/**
 * Initialize security headers
 */
function setSecurityHeaders() {
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Prevent clickjacking
    header("X-Frame-Options: DENY");
    
    // Enable XSS protection
    header("X-XSS-Protection: 1; mode=block");
    
    // HSTS - Force HTTPS (only in production)
    if (IS_PRODUCTION) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Permissions Policy (formerly Feature Policy)
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * Check and update session timeout
 * @return bool True if session is valid, false if expired
 */
function checkSessionTimeout() {
    if (!isset($_SESSION['user_id'])) {
        return true; // Not logged in, no timeout to check
    }
    
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Session expired
        session_destroy();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Force HTTPS in production
 */
function enforceHTTPS() {
    if (IS_PRODUCTION && 
        (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirect, true, 301);
        exit;
    }
}

/**
 * Configure error handling based on environment
 */
function configureErrorHandling() {
    if (IS_PRODUCTION) {
        // Production: Hide errors from users, log them
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);
    } else {
        // Development: Show all errors
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }
}

/**
 * Sanitize input data
 * @param mixed $data The data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    if (is_string($data)) {
        // Remove null bytes
        $data = str_replace("\0", '', $data);
        
        // Trim whitespace
        $data = trim($data);
        
        // Convert special characters to HTML entities
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Validate password strength
 * @param string $password The password to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long.";
    }
    
    if (REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    
    if (REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    
    if (REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    if (REQUIRE_SPECIAL_CHARS && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Log security event
 * @param PDO $db Database connection
 * @param string $event Event type
 * @param string $description Event description
 * @param array $context Additional context
 */
function logSecurityEvent($db, $event, $description, $context = []) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO activity_logs (user_id, role, action, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        
        $userId = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['role'] ?? 'guest';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $contextJson = !empty($context) ? ' | ' . json_encode($context) : '';
        
        $stmt->execute([
            $userId,
            $role,
            $event,
            $description . $contextJson,
            $ipAddress
        ]);
    } catch (Exception $e) {
        // Silent fail - logging shouldn't break the application
        error_log("Failed to log security event: " . $e->getMessage());
    }
}

?>
