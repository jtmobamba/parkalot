<?php
class AuthController {
    private $userDAO;

    public function __construct($db) {
        $this->userDAO = DAOFactory::userDAO($db);
    }

    private function ensureSessionStarted() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function errorResponse($code, $message, $fieldErrors = []) {
        // Keep a simple shape so existing frontend keeps working:
        // - `error` is a user-friendly string
        // - `code` helps the UI branch on specific cases
        // - `fields` (optional) contains per-field error messages
        return [
            "error" => $message,
            "code" => $code,
            "fields" => (object)$fieldErrors
        ];
    }

    private function normalizeEmail($email) {
        return strtolower(trim((string)$email));
    }

    private function validateLogin($data) {
        $errors = [];

        $email = isset($data->email) ? $this->normalizeEmail($data->email) : "";
        $password = isset($data->password) ? (string)$data->password : "";

        if ($email === "") $errors["email"] = "Email is required.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors["email"] = "Please enter a valid email address.";

        if (trim($password) === "") $errors["password"] = "Password is required.";

        return [$email, $password, $errors];
    }

    private function validateRegister($data) {
        $errors = [];

        $name = isset($data->name) ? trim((string)$data->name) : "";
        $email = isset($data->email) ? $this->normalizeEmail($data->email) : "";
        $password = isset($data->password) ? (string)$data->password : "";

        if ($name === "") $errors["name"] = "Full name is required.";
        elseif (mb_strlen($name) < 2) $errors["name"] = "Full name must be at least 2 characters.";

        if ($email === "") $errors["email"] = "Email is required.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors["email"] = "Please enter a valid email address.";

        if (trim($password) === "") $errors["password"] = "Password is required.";
        elseif (strlen($password) < 8) $errors["password"] = "Password must be at least 8 characters.";
        else {
            // Enhanced password validation
            if (!preg_match('/[A-Z]/', $password)) {
                $errors["password"] = "Password must contain at least one uppercase letter.";
            } elseif (!preg_match('/[a-z]/', $password)) {
                $errors["password"] = "Password must contain at least one lowercase letter.";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $errors["password"] = "Password must contain at least one number.";
            }
        }

        return [$name, $email, $password, $errors];
    }

    public function login($data) {
        $this->ensureSessionStarted();

        if (!is_object($data)) {
            return $this->errorResponse("bad_json", "Invalid request.");
        }

        [$email, $password, $fieldErrors] = $this->validateLogin($data);
        if (!empty($fieldErrors)) {
            return $this->errorResponse("validation", "Please fix the highlighted fields.", $fieldErrors);
        }

        // Check login rate limiting
        $rateLimitError = $this->checkLoginRateLimit($email);
        if ($rateLimitError) {
            return $rateLimitError;
        }

        $user = $this->userDAO->findByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if email is verified
            if (!$user['email_verified']) {
                // Create session for OTP verification
                $_SESSION['pending_user_id'] = $user['user_id'];
                $_SESSION['pending_role'] = $user['role'] ?? 'customer';
                $_SESSION['pending_user_name'] = $user['full_name'];
                $_SESSION['pending_email'] = $user['email'];
                
                return $this->errorResponse(
                    "email_not_verified",
                    "Please verify your email address to continue. An OTP will be sent to your email.",
                    []
                );
            }
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'] ?? 'customer';
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $this->userDAO->updateLastLogin($user['user_id']);
            
            // Log activity
            $this->logActivity($user['user_id'], $user['role'], 'login', 'User logged in');
            
            return [
                "message" => "Login successful",
                "role" => $_SESSION['role'],
                "user_name" => $_SESSION['user_name']
            ];
        }

        // Log failed login attempt
        $this->logActivity(null, 'guest', 'failed_login', "Failed login attempt for: {$email}");
        
        // Intentionally generic to avoid account enumeration.
        return $this->errorResponse("invalid_credentials", "Invalid email or password.");
    }

    public function register($data) {
        $this->ensureSessionStarted();

        if (!is_object($data)) {
            return $this->errorResponse("bad_json", "Invalid request.");
        }

        [$name, $email, $password, $fieldErrors] = $this->validateRegister($data);
        if (!empty($fieldErrors)) {
            return $this->errorResponse("validation", "Please fix the highlighted fields.", $fieldErrors);
        }

        $existing = $this->userDAO->findByEmail($email);
        if ($existing) {
            return $this->errorResponse("account_exists", "Account already exists.", [
                "email" => "An account with this email already exists."
            ]);
        }

        $userId = $this->userDAO->create($name, $email, $password);
        if (!$userId) {
            return $this->errorResponse("server_error", "Unable to create account. Please try again.");
        }

        // Get the newly created user
        $newUser = $this->userDAO->findByEmail($email);
        
        if ($newUser) {
            // Create pending session for email verification
            $_SESSION['pending_user_id'] = $newUser['user_id'];
            $_SESSION['pending_role'] = $newUser['role'] ?? 'customer';
            $_SESSION['pending_user_name'] = $newUser['full_name'];
            $_SESSION['pending_email'] = $newUser['email'];
            
            return [
                "message" => "Registration successful. Please verify your email.",
                "code" => "verification_required",
                "requires_verification" => true
            ];
        }

        return ["message" => "Registration successful. You may now login."];
    }

    public function logout() {
        $this->ensureSessionStarted();
        
        // Log activity before destroying session
        if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
            $this->logActivity($_SESSION['user_id'], $_SESSION['role'], 'logout', 'User logged out');
        }
        
        session_destroy();
        return ["message" => "Logged out"];
    }

    /**
     * Check if user has required role
     */
    public function hasRole($requiredRoles) {
        $this->ensureSessionStarted();
        
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        if (is_array($requiredRoles)) {
            return in_array($_SESSION['role'], $requiredRoles);
        }
        
        return $_SESSION['role'] === $requiredRoles;
    }

    /**
     * Get current user info
     */
    public function getCurrentUser() {
        $this->ensureSessionStarted();
        
        if (!isset($_SESSION['user_id'])) {
            return ['error' => 'Not authenticated'];
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? 'customer',
            'user_name' => $_SESSION['user_name'] ?? 'User'
        ];
    }

    /**
     * Check login rate limiting to prevent brute force attacks
     */
    private function checkLoginRateLimit($email) {
        try {
            $stmt = $this->userDAO->db->prepare("
                SELECT COUNT(*) as attempts
                FROM activity_logs
                WHERE action = 'failed_login' 
                AND description LIKE ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $stmt->execute(["%{$email}%"]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['attempts'] >= 5) {
                return $this->errorResponse(
                    "rate_limited", 
                    "Too many failed login attempts. Please try again in 15 minutes."
                );
            }
        } catch (Exception $e) {
            // If rate limit check fails, continue with login
            error_log("Rate limit check failed: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Log user activity
     */
    private function logActivity($userId, $role, $action, $description) {
        try {
            $stmt = $this->userDAO->db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->execute([$userId, $role, $action, $description, $ipAddress]);
        } catch (Exception $e) {
            // Silent fail - logging shouldn't break authentication
        }
    }
}