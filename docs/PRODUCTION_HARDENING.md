# ParkaLot System - Production Hardening Guide

## Overview

This document details the production hardening measures implemented in the ParkaLot Parking Management System, demonstrating professional software engineering practices.

---

## 1. Defensive Programming

### 1.1 Input Validation

All user inputs are validated at multiple layers:

```php
// Example: Email validation in AuthController.php
private function validateLogin($data) {
    $errors = [];

    $email = isset($data->email) ? $this->normalizeEmail($data->email) : "";
    $password = isset($data->password) ? (string)$data->password : "";

    if ($email === "")
        $errors["email"] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors["email"] = "Please enter a valid email address.";

    if (trim($password) === "")
        $errors["password"] = "Password is required.";

    return [$email, $password, $errors];
}
```

### 1.2 Null Safety Checks

```php
// Example: Safe array access
$value = isset($data['key']) ? $data['key'] : 'default';
$value = $data['key'] ?? 'default'; // PHP 7+ null coalescing

// Example: Safe object property access
$email = isset($data->email) ? trim($data->email) : '';
```

### 1.3 Type Safety

```php
// Explicit type casting
$userId = (int)$_SESSION['user_id'];
$amount = (float)$data->amount;
$isActive = (bool)$data->active;
```

---

## 2. Error Handling

### 2.1 Centralized Error Handling

```php
// config/security.php
function configureErrorHandling() {
    // Production: Log errors, don't display
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    // Set custom error handler
    set_error_handler(function($severity, $message, $file, $line) {
        error_log("[$severity] $message in $file:$line");
        return true;
    });
}
```

### 2.2 Try-Catch Blocks

```php
// Example: Database operation with error handling
try {
    $stmt = $db->prepare("INSERT INTO reservations ...");
    $stmt->execute($params);
    return ['success' => true, 'id' => $db->lastInsertId()];
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    return ['error' => 'Unable to complete reservation. Please try again.'];
}
```

### 2.3 Graceful Degradation

```php
// Example: Email service with fallback
try {
    $emailService->send($to, $subject, $body);
    return ['success' => true];
} catch (Exception $e) {
    // Log error but don't fail the main operation
    error_log("Email failed: " . $e->getMessage());
    return ['success' => true, 'warning' => 'Email notification failed'];
}
```

---

## 3. Configuration Management

### 3.1 Environment Configuration

```
config/
├── database.php      # Database connection settings
├── security.php      # Security constants and functions
├── email_config.php  # SMTP configuration
└── vehicle_api.php   # External API configuration
```

### 3.2 Configuration Structure

```php
// config/security.php - Security constants
define('SESSION_TIMEOUT', 3600);           // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);           // Rate limiting
define('LOGIN_LOCKOUT_TIME', 900);         // 15 minutes
define('OTP_EXPIRY_TIME', 600);            // 10 minutes
define('PASSWORD_MIN_LENGTH', 8);
```

### 3.3 Environment-Specific Settings

```php
// Determine environment
$environment = getenv('APP_ENV') ?: 'development';

if ($environment === 'production') {
    ini_set('display_errors', '0');
    define('DEBUG_MODE', false);
} else {
    ini_set('display_errors', '1');
    define('DEBUG_MODE', true);
}
```

---

## 4. Database Hardening

### 4.1 Connection Configuration

```php
// config/database.php
class Database {
    public static function connect() {
        return new PDO(
            "mysql:host=localhost;dbname=parkalots;charset=utf8mb4",
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
            ]
        );
    }
}
```

### 4.2 Query Safety

```php
// ✅ SAFE: Prepared statements
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ UNSAFE: String concatenation (NEVER DO THIS)
// $query = "SELECT * FROM users WHERE email = '$email'";
```

---

## 5. Session Hardening

### 5.1 Session Configuration

```php
// Session security settings
ini_set('session.cookie_httponly', 1);     // Prevent XSS access
ini_set('session.cookie_secure', 1);       // HTTPS only
ini_set('session.use_strict_mode', 1);     // Reject uninitialized session IDs
ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
```

### 5.2 Session Timeout

```php
// Check session timeout
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}
```

---

## 6. API Hardening

### 6.1 Request Validation

```php
// Validate JSON input
$data = json_decode(file_get_contents("php://input"));
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}
```

### 6.2 Response Headers

```php
// Set security headers
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Cache-Control: no-store, no-cache, must-revalidate");
```

### 6.3 HTTP Status Codes

```php
// Appropriate HTTP status codes
switch ($payload['code']) {
    case 'validation':
        http_response_code(400); // Bad Request
        break;
    case 'invalid_credentials':
        http_response_code(401); // Unauthorized
        break;
    case 'rate_limited':
        http_response_code(429); // Too Many Requests
        break;
    default:
        http_response_code(200); // OK
}
```

---

## 7. Logging and Monitoring

### 7.1 Activity Logging

```php
// Log all significant actions
$stmt = $db->prepare(
    "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->execute([
    $userId,
    $role,
    'login',
    'User logged in successfully',
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);
```

### 7.2 Error Logging

```php
// Centralized error logging
function logError($message, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . " | " . $message;
    if (!empty($context)) {
        $logEntry .= " | Context: " . json_encode($context);
    }
    error_log($logEntry);
}
```

---

## 8. File Upload Security

### 8.1 Upload Validation

```php
// Validate uploaded files
$allowedTypes = ['application/pdf', 'application/msword'];
$maxSize = 10 * 1024 * 1024; // 10MB

if (!in_array($_FILES['file']['type'], $allowedTypes)) {
    return ['error' => 'Invalid file type'];
}

if ($_FILES['file']['size'] > $maxSize) {
    return ['error' => 'File too large'];
}
```

### 8.2 Secure Storage

```php
// Generate safe filename
$extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
$safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
$uploadPath = __DIR__ . '/../uploads/' . $safeFilename;
```

---

## 9. Production Checklist

### Pre-Deployment

- [ ] All debug statements removed
- [ ] Error display disabled
- [ ] Secure database credentials configured
- [ ] HTTPS enabled
- [ ] Security headers configured
- [ ] File permissions set correctly

### Runtime

- [ ] Rate limiting active
- [ ] Session timeout configured
- [ ] Activity logging enabled
- [ ] Error logging enabled
- [ ] Backup procedures in place

### Monitoring

- [ ] Error logs monitored
- [ ] Activity logs reviewed
- [ ] Performance metrics tracked
- [ ] Security alerts configured

---

## 10. Future Enhancements

| Enhancement | Priority | Status |
|-------------|----------|--------|
| Two-factor authentication | High | Planned |
| API rate limiting per endpoint | Medium | Planned |
| Request signing | Medium | Planned |
| Automated security scanning | High | Planned |
| Log aggregation service | Low | Planned |

---

**Document Version**: 1.0
**Last Updated**: 2026-02-03
