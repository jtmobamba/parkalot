<?php
/**
 * CSRF Protection Utility
 * Generates and validates CSRF tokens for state-changing operations
 */
class CSRFProtection {
    
    /**
     * Generate a CSRF token and store it in session
     * @return string The CSRF token
     */
    public static function generateToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate a CSRF token against the session token
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Rotate the CSRF token (for additional security)
     */
    public static function rotateToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get the current CSRF token (generate if needed)
     * @return string The CSRF token
     */
    public static function getToken() {
        return self::generateToken();
    }
    
    /**
     * Validate token from request headers or POST data
     * @return bool True if valid, false otherwise
     */
    public static function validateRequest() {
        // Check X-CSRF-Token header first
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        // Fall back to POST data
        if (!$token && isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        }
        
        // Fall back to JSON body
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['csrf_token'] ?? null;
        }
        
        return self::validateToken($token);
    }
}
?>
