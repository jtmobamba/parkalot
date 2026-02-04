<?php
/**
 * Production Hardening Configuration
 * Defensive programming patterns and security hardening utilities
 */

// ═══════════════════════════════════════════════════════════════════
// DEFENSIVE PROGRAMMING SETTINGS
// ═══════════════════════════════════════════════════════════════════

// Input validation limits
define('MAX_INPUT_LENGTH', 10000);          // Maximum input string length
define('MAX_ARRAY_DEPTH', 5);               // Maximum nested array depth
define('MAX_REQUEST_SIZE', 1048576);        // 1MB max request size

// API rate limiting
define('API_RATE_LIMIT_REQUESTS', 100);     // Requests per window
define('API_RATE_LIMIT_WINDOW', 60);        // Window in seconds

// Database query limits
define('MAX_QUERY_RESULTS', 1000);          // Maximum rows per query
define('QUERY_TIMEOUT', 30);                // Query timeout in seconds

/**
 * Defensive Programming Utilities
 */
class DefensiveProgramming {

    /**
     * Safely get value from array with type validation
     * @param array $array Source array
     * @param string $key Key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @param string $type Expected type (string, int, float, bool, array)
     * @return mixed Validated value or default
     */
    public static function safeGet($array, $key, $default = null, $type = null) {
        if (!is_array($array) || !array_key_exists($key, $array)) {
            return $default;
        }

        $value = $array[$key];

        // Type validation and casting
        if ($type !== null) {
            switch ($type) {
                case 'string':
                    return is_string($value) ? trim($value) : (string)$value;
                case 'int':
                    return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int)$value : $default;
                case 'float':
                    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? (float)$value : $default;
                case 'bool':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
                case 'array':
                    return is_array($value) ? $value : $default;
                case 'email':
                    return filter_var($value, FILTER_VALIDATE_EMAIL) ?: $default;
            }
        }

        return $value;
    }

    /**
     * Safely get value from object with null checks
     * @param object|null $object Source object
     * @param string $property Property name
     * @param mixed $default Default value
     * @return mixed Value or default
     */
    public static function safeObjectGet($object, $property, $default = null) {
        if (!is_object($object)) {
            return $default;
        }
        return isset($object->$property) ? $object->$property : $default;
    }

    /**
     * Validate input length to prevent buffer overflow attacks
     * @param string $input Input string
     * @param int $maxLength Maximum allowed length
     * @return string Truncated input if necessary
     */
    public static function limitLength($input, $maxLength = null) {
        $maxLength = $maxLength ?? MAX_INPUT_LENGTH;
        if (!is_string($input)) {
            return '';
        }
        // Use mb_substr if available, fallback to substr
        if (function_exists('mb_substr')) {
            return mb_substr($input, 0, $maxLength);
        }
        return substr($input, 0, $maxLength);
    }

    /**
     * Validate array depth to prevent stack overflow
     * @param array $array Array to check
     * @param int $maxDepth Maximum allowed depth
     * @param int $currentDepth Current depth (internal)
     * @return bool True if within limits
     */
    public static function validateArrayDepth($array, $maxDepth = null, $currentDepth = 0) {
        $maxDepth = $maxDepth ?? MAX_ARRAY_DEPTH;

        if ($currentDepth > $maxDepth) {
            return false;
        }

        if (!is_array($array)) {
            return true;
        }

        foreach ($array as $value) {
            if (is_array($value)) {
                if (!self::validateArrayDepth($value, $maxDepth, $currentDepth + 1)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Sanitize input data recursively
     * @param mixed $data Input data
     * @return mixed Sanitized data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            // Trim whitespace
            $data = trim($data);
            // Limit length
            $data = self::limitLength($data);
            // Encode special characters
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $data;
    }

    /**
     * Validate request size
     * @return bool True if within limits
     */
    public static function validateRequestSize() {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
        return $contentLength <= MAX_REQUEST_SIZE;
    }
}

/**
 * Error Handling Utilities
 */
class ErrorHandler {

    private static $initialized = false;

    /**
     * Initialize error handling
     */
    public static function initialize() {
        if (self::$initialized) return;

        // Set error reporting
        error_reporting(E_ALL);

        // Production vs Development
        $isProduction = getenv('APP_ENV') === 'production';

        ini_set('display_errors', $isProduction ? '0' : '1');
        ini_set('display_startup_errors', $isProduction ? '0' : '1');
        ini_set('log_errors', '1');
        ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

        // Set custom error handler
        set_error_handler([self::class, 'handleError']);

        // Set custom exception handler
        set_exception_handler([self::class, 'handleException']);

        // Register shutdown function for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$initialized = true;
    }

    /**
     * Custom error handler
     */
    public static function handleError($severity, $message, $file, $line) {
        $errorTypes = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_NOTICE => 'Notice',
            E_STRICT => 'Strict',
            E_DEPRECATED => 'Deprecated'
        ];

        $type = $errorTypes[$severity] ?? 'Unknown';
        $logMessage = "[$type] $message in $file:$line";

        self::log($logMessage, 'error');

        // Don't execute PHP's internal error handler
        return true;
    }

    /**
     * Custom exception handler
     */
    public static function handleException($exception) {
        $message = sprintf(
            "[Exception] %s in %s:%d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        self::log($message, 'exception');

        // Return generic error to user
        if (getenv('APP_ENV') === 'production') {
            http_response_code(500);
            if (self::isJsonRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'An unexpected error occurred']);
            } else {
                echo 'An unexpected error occurred. Please try again later.';
            }
        }
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * Log message to file
     */
    public static function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $logEntry = "[$timestamp] [$level] [$ip] $message" . PHP_EOL;

        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        error_log($logEntry, 3, "$logDir/app.log");
    }

    /**
     * Check if request expects JSON response
     */
    private static function isJsonRequest() {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'application/json') !== false;
    }

    /**
     * Create safe error response
     * @param string $userMessage Message safe to show user
     * @param string $logMessage Detailed message for logs
     * @param int $httpCode HTTP status code
     * @return array Error response array
     */
    public static function createErrorResponse($userMessage, $logMessage = null, $httpCode = 400) {
        if ($logMessage) {
            self::log($logMessage, 'error');
        }

        http_response_code($httpCode);

        return [
            'error' => $userMessage,
            'code' => $httpCode
        ];
    }
}

/**
 * Database Hardening Utilities
 */
class DatabaseHardening {

    /**
     * Create hardened PDO connection
     * @param string $host Database host
     * @param string $dbname Database name
     * @param string $user Username
     * @param string $password Password
     * @return PDO Configured PDO instance
     */
    public static function createConnection($host, $dbname, $user, $password) {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

        $options = [
            // Throw exceptions on errors
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Return associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Use native prepared statements (prevents SQL injection)
            PDO::ATTR_EMULATE_PREPARES => false,

            // Set connection timeout
            PDO::ATTR_TIMEOUT => QUERY_TIMEOUT,

            // Persistent connections for performance
            PDO::ATTR_PERSISTENT => false,
        ];

        $pdo = new PDO($dsn, $user, $password, $options);

        // Additional hardening
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

        return $pdo;
    }

    /**
     * Execute query with automatic parameter binding
     * @param PDO $db Database connection
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement Executed statement
     */
    public static function safeQuery($db, $query, $params = []) {
        $stmt = $db->prepare($query);

        // Bind parameters with appropriate types
        foreach ($params as $key => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }

            $paramKey = is_int($key) ? $key + 1 : $key;
            $stmt->bindValue($paramKey, $value, $type);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Safely fetch limited results
     * @param PDOStatement $stmt Executed statement
     * @param int $limit Maximum rows to fetch
     * @return array Results
     */
    public static function safeFetchAll($stmt, $limit = null) {
        $limit = $limit ?? MAX_QUERY_RESULTS;
        $results = [];
        $count = 0;

        while ($row = $stmt->fetch() and $count < $limit) {
            $results[] = $row;
            $count++;
        }

        return $results;
    }

    /**
     * Validate and sanitize table/column names
     * @param string $name Table or column name
     * @return string|false Sanitized name or false if invalid
     */
    public static function sanitizeIdentifier($name) {
        // Only allow alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            return false;
        }
        return $name;
    }
}

/**
 * Session Hardening Utilities
 */
class SessionHardening {

    /**
     * Initialize hardened session
     */
    public static function initialize() {
        // Only configure if session not started
        if (session_status() === PHP_SESSION_NONE) {
            // Security settings
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', getenv('APP_ENV') === 'production' ? 1 : 0);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_only_cookies', 1);
            ini_set('session.use_trans_sid', 0);

            // Session ID settings
            ini_set('session.sid_length', 48);
            ini_set('session.sid_bits_per_character', 6);

            // Garbage collection
            ini_set('session.gc_maxlifetime', 3600);
            ini_set('session.gc_probability', 1);
            ini_set('session.gc_divisor', 100);

            session_start();
        }
    }

    /**
     * Regenerate session ID (call after login)
     */
    public static function regenerate() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Check session timeout and fingerprint
     * @return bool True if session is valid
     */
    public static function validate() {
        // Check timeout
        if (isset($_SESSION['last_activity'])) {
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
            if (time() - $_SESSION['last_activity'] > $timeout) {
                self::destroy();
                return false;
            }
        }

        // Update activity timestamp
        $_SESSION['last_activity'] = time();

        // Validate fingerprint
        if (isset($_SESSION['fingerprint'])) {
            if ($_SESSION['fingerprint'] !== self::generateFingerprint()) {
                self::destroy();
                return false;
            }
        } else {
            $_SESSION['fingerprint'] = self::generateFingerprint();
        }

        return true;
    }

    /**
     * Generate session fingerprint
     */
    private static function generateFingerprint() {
        $data = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $data .= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        return hash('sha256', $data);
    }

    /**
     * Destroy session securely
     */
    public static function destroy() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Set secure session variable
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session variable safely
     */
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
}

/**
 * API Hardening Utilities
 */
class APIHardening {

    private static $rateLimitStore = [];

    /**
     * Validate and parse JSON request
     * @return object|array|null Parsed data or null on failure
     */
    public static function parseJsonRequest() {
        // Check content type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') === false) {
            return null;
        }

        // Check request size
        if (!DefensiveProgramming::validateRequestSize()) {
            return null;
        }

        $input = file_get_contents('php://input');
        $data = json_decode($input);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // CORS headers (configure as needed)
        if (getenv('APP_ENV') !== 'production') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
        }
    }

    /**
     * Check rate limiting
     * @param string $identifier Client identifier (IP or user ID)
     * @return bool True if within limits
     */
    public static function checkRateLimit($identifier = null) {
        $identifier = $identifier ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = 'rate_' . md5($identifier);
        $now = time();
        $window = API_RATE_LIMIT_WINDOW;
        $limit = API_RATE_LIMIT_REQUESTS;

        // Simple in-memory rate limiting (use Redis in production)
        if (!isset(self::$rateLimitStore[$key])) {
            self::$rateLimitStore[$key] = [
                'count' => 0,
                'window_start' => $now
            ];
        }

        $data = &self::$rateLimitStore[$key];

        // Reset window if expired
        if ($now - $data['window_start'] > $window) {
            $data['count'] = 0;
            $data['window_start'] = $now;
        }

        $data['count']++;

        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . max(0, $limit - $data['count']));
        header('X-RateLimit-Reset: ' . ($data['window_start'] + $window));

        return $data['count'] <= $limit;
    }

    /**
     * Send JSON response with proper status code
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     */
    public static function respond($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send error response
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string $code Error code
     */
    public static function respondError($message, $statusCode = 400, $code = 'error') {
        self::respond([
            'error' => $message,
            'code' => $code
        ], $statusCode);
    }

    /**
     * Validate required fields in request
     * @param object|array $data Request data
     * @param array $required Required field names
     * @return array Missing fields
     */
    public static function validateRequired($data, $required) {
        $missing = [];
        $data = (array)$data;

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}

/**
 * Production Deployment Checklist
 */
class DeploymentChecklist {

    /**
     * Run all production readiness checks
     * @return array Check results
     */
    public static function runChecks() {
        $checks = [];

        // Check 1: Debug mode disabled
        $checks['debug_disabled'] = [
            'name' => 'Debug mode disabled',
            'passed' => getenv('APP_ENV') === 'production' || !defined('DEBUG_MODE') || !DEBUG_MODE,
            'critical' => true
        ];

        // Check 2: Error display disabled
        $checks['error_display'] = [
            'name' => 'Error display disabled',
            'passed' => ini_get('display_errors') === '0' || ini_get('display_errors') === '',
            'critical' => true
        ];

        // Check 3: HTTPS enforced
        $checks['https'] = [
            'name' => 'HTTPS connection',
            'passed' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'critical' => true
        ];

        // Check 4: Session security
        $checks['session_httponly'] = [
            'name' => 'Session cookies HTTP-only',
            'passed' => ini_get('session.cookie_httponly') == 1,
            'critical' => true
        ];

        // Check 5: Security headers function exists
        $checks['security_headers'] = [
            'name' => 'Security headers configured',
            'passed' => function_exists('setSecurityHeaders'),
            'critical' => true
        ];

        // Check 6: Logs directory writable
        $logDir = __DIR__ . '/../logs';
        $checks['logs_writable'] = [
            'name' => 'Logs directory writable',
            'passed' => is_dir($logDir) && is_writable($logDir),
            'critical' => false
        ];

        // Check 7: Uploads directory permissions
        $uploadDir = __DIR__ . '/../uploads';
        $checks['uploads_secure'] = [
            'name' => 'Uploads directory exists',
            'passed' => is_dir($uploadDir),
            'critical' => false
        ];

        // Check 8: Database connection
        $checks['database'] = [
            'name' => 'Database connection',
            'passed' => self::checkDatabaseConnection(),
            'critical' => true
        ];

        // Check 9: Required PHP extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
        $missingExtensions = array_filter($requiredExtensions, fn($ext) => !extension_loaded($ext));
        $checks['php_extensions'] = [
            'name' => 'Required PHP extensions',
            'passed' => empty($missingExtensions),
            'critical' => true,
            'details' => empty($missingExtensions) ? 'All present' : 'Missing: ' . implode(', ', $missingExtensions)
        ];

        // Check 10: PHP version
        $checks['php_version'] = [
            'name' => 'PHP version >= 8.0',
            'passed' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'critical' => true,
            'details' => 'Current: ' . PHP_VERSION
        ];

        return $checks;
    }

    /**
     * Check database connection
     */
    private static function checkDatabaseConnection() {
        try {
            require_once __DIR__ . '/database.php';
            $db = Database::connect();
            return $db instanceof PDO;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get deployment readiness status
     * @return array Status summary
     */
    public static function getStatus() {
        $checks = self::runChecks();
        $passed = array_filter($checks, fn($c) => $c['passed']);
        $failed = array_filter($checks, fn($c) => !$c['passed']);
        $criticalFailed = array_filter($failed, fn($c) => $c['critical'] ?? false);

        return [
            'ready' => empty($criticalFailed),
            'total_checks' => count($checks),
            'passed' => count($passed),
            'failed' => count($failed),
            'critical_failures' => count($criticalFailed),
            'checks' => $checks
        ];
    }
}
