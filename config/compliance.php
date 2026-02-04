<?php
/**
 * Compliance Configuration
 * GDPR and PCI DSS compliance helpers for the ParkaLot system
 */

// ═══════════════════════════════════════════════════════════════════
// GDPR COMPLIANCE SETTINGS
// ═══════════════════════════════════════════════════════════════════

// Data retention periods (in days)
define('GDPR_SESSION_DATA_RETENTION', 1);           // Session data: 1 day
define('GDPR_ACTIVITY_LOG_RETENTION', 90);          // Activity logs: 90 days
define('GDPR_RESERVATION_DATA_RETENTION', 365);     // Reservation data: 1 year
define('GDPR_ACCOUNT_INACTIVE_PERIOD', 730);        // Inactive accounts: 2 years

// Data minimization - required fields only
define('GDPR_REQUIRED_USER_FIELDS', [
    'full_name',
    'email',
    'password_hash',
    'role'
]);

// Optional fields that require explicit consent
define('GDPR_OPTIONAL_FIELDS', [
    'phone_number',
    'vehicle_registration',
    'payment_preferences'
]);

// ═══════════════════════════════════════════════════════════════════
// PCI DSS COMPLIANCE SETTINGS
// ═══════════════════════════════════════════════════════════════════

// Payment data handling
define('PCI_STORE_CARD_DATA', false);               // NEVER store full card numbers
define('PCI_STORE_CVV', false);                     // NEVER store CVV
define('PCI_TOKENIZATION_REQUIRED', true);          // Always use tokenization
define('PCI_LOG_PAYMENT_ATTEMPTS', true);           // Log payment attempts (not card data)

// Allowed payment gateways (PCI DSS compliant)
define('PCI_APPROVED_GATEWAYS', [
    'stripe',
    'paypal',
    'square'
]);

/**
 * GDPR Compliance Helper Class
 */
class GDPRCompliance {

    /**
     * Sanitize user data to ensure only allowed fields are stored
     * @param array $userData Raw user data
     * @return array Sanitized user data
     */
    public static function sanitizeUserData($userData) {
        $allowedFields = array_merge(
            GDPR_REQUIRED_USER_FIELDS,
            GDPR_OPTIONAL_FIELDS,
            ['user_id', 'created_at', 'updated_at', 'email_verified', 'last_login']
        );

        return array_intersect_key($userData, array_flip($allowedFields));
    }

    /**
     * Export user data in portable format (GDPR Right to Portability)
     * @param array $userData User's data
     * @return string JSON formatted data
     */
    public static function exportUserData($userData) {
        $exportData = [
            'export_date' => date('Y-m-d H:i:s'),
            'data_controller' => 'ParkaLot System',
            'user_data' => self::sanitizeUserData($userData),
            'format_version' => '1.0'
        ];

        return json_encode($exportData, JSON_PRETTY_PRINT);
    }

    /**
     * Anonymize user data (for GDPR Right to Erasure)
     * @param array $userData User's data
     * @return array Anonymized data
     */
    public static function anonymizeUserData($userData) {
        return [
            'user_id' => $userData['user_id'] ?? null,
            'full_name' => 'ANONYMIZED_USER',
            'email' => 'anonymized_' . md5(uniqid()) . '@deleted.local',
            'password_hash' => '',
            'role' => 'deleted',
            'email_verified' => 0,
            'anonymized_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Check if data retention period has expired
     * @param string $createdAt Creation timestamp
     * @param int $retentionDays Retention period in days
     * @return bool True if expired
     */
    public static function isRetentionExpired($createdAt, $retentionDays) {
        $createdTime = strtotime($createdAt);
        $expiryTime = $createdTime + ($retentionDays * 86400);
        return time() > $expiryTime;
    }

    /**
     * Get consent requirements for data collection
     * @return array Consent requirements
     */
    public static function getConsentRequirements() {
        return [
            'essential' => [
                'description' => 'Essential data for account and parking services',
                'fields' => GDPR_REQUIRED_USER_FIELDS,
                'required' => true
            ],
            'optional' => [
                'description' => 'Additional data for enhanced services',
                'fields' => GDPR_OPTIONAL_FIELDS,
                'required' => false
            ],
            'marketing' => [
                'description' => 'Marketing and promotional communications',
                'fields' => ['email'],
                'required' => false
            ]
        ];
    }

    /**
     * Log data access for accountability
     * @param PDO $db Database connection
     * @param int $userId User ID
     * @param string $accessType Type of access
     * @param string $dataAccessed Description of data accessed
     */
    public static function logDataAccess($db, $userId, $accessType, $dataAccessed) {
        try {
            $stmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address, created_at)
                 VALUES (?, 'system', ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId,
                'gdpr_' . $accessType,
                'Data accessed: ' . $dataAccessed,
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);
        } catch (Exception $e) {
            error_log("GDPR log failed: " . $e->getMessage());
        }
    }
}

/**
 * PCI DSS Compliance Helper Class
 */
class PCIDSSCompliance {

    /**
     * Validate that no card data is being stored
     * @param array $data Data to check
     * @return bool True if compliant (no card data)
     * @throws Exception If card data detected
     */
    public static function validateNoCardData($data) {
        // Card number patterns - specific formats only
        $cardPatterns = [
            '/\b(4[0-9]{12}(?:[0-9]{3})?)\b/',             // Visa (starts with 4, 13-16 digits)
            '/\b(5[1-5][0-9]{14})\b/',                     // Mastercard (starts with 51-55)
            '/\b(3[47][0-9]{13})\b/',                      // Amex (starts with 34/37)
            '/\b(6(?:011|5[0-9]{2})[0-9]{12})\b/',         // Discover
            '/\b(?:\d{4}[-\s]?){3}\d{4}\b/',               // Formatted card numbers
        ];

        // Check for explicit card-related field names
        $sensitiveFields = ['card_number', 'cardNumber', 'cc_number', 'cvv', 'cvc', 'card_cvv'];

        // Check field names
        if (is_array($data)) {
            foreach ($sensitiveFields as $field) {
                if (array_key_exists($field, $data)) {
                    error_log("PCI DSS ALERT: Sensitive field name detected: " . $field);
                    throw new Exception("Card data storage is not permitted");
                }
            }
        }

        $dataString = json_encode($data);

        // Check for card number patterns
        foreach ($cardPatterns as $pattern) {
            if (preg_match($pattern, $dataString)) {
                error_log("PCI DSS ALERT: Potential card data detected in storage attempt");
                throw new Exception("Card data storage is not permitted");
            }
        }

        return true;
    }

    /**
     * Mask card number for display (show only last 4 digits)
     * @param string $cardNumber Full card number
     * @return string Masked card number
     */
    public static function maskCardNumber($cardNumber) {
        $cleaned = preg_replace('/[^0-9]/', '', $cardNumber);
        if (strlen($cleaned) < 4) {
            return '****';
        }
        return '****-****-****-' . substr($cleaned, -4);
    }

    /**
     * Generate a secure transaction reference (not card data)
     * @return string Transaction reference
     */
    public static function generateTransactionRef() {
        return 'TXN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Validate payment gateway is PCI DSS compliant
     * @param string $gateway Gateway name
     * @return bool True if approved
     */
    public static function isApprovedGateway($gateway) {
        return in_array(strtolower($gateway), PCI_APPROVED_GATEWAYS);
    }

    /**
     * Create PCI-compliant payment record (no sensitive data)
     * @param array $paymentData Payment information
     * @return array Sanitized payment record
     */
    public static function createPaymentRecord($paymentData) {
        // Ensure no card data is stored
        self::validateNoCardData($paymentData);

        return [
            'transaction_ref' => self::generateTransactionRef(),
            'amount' => $paymentData['amount'] ?? 0,
            'currency' => $paymentData['currency'] ?? 'GBP',
            'gateway' => $paymentData['gateway'] ?? 'stripe',
            'gateway_transaction_id' => $paymentData['gateway_transaction_id'] ?? null,
            'status' => $paymentData['status'] ?? 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            // NEVER store: card_number, cvv, expiry_date
        ];
    }

    /**
     * Log payment attempt (PCI DSS Req 10)
     * @param PDO $db Database connection
     * @param int $userId User ID
     * @param string $action Payment action
     * @param string $result Result of action
     */
    public static function logPaymentAttempt($db, $userId, $action, $result) {
        if (!PCI_LOG_PAYMENT_ATTEMPTS) return;

        try {
            $stmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address, created_at)
                 VALUES (?, 'payment', ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId,
                'payment_' . $action,
                'Payment action result: ' . $result,
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);
        } catch (Exception $e) {
            error_log("PCI payment log failed: " . $e->getMessage());
        }
    }
}

/**
 * Compliance Audit Helper
 */
class ComplianceAudit {

    /**
     * Generate compliance status report
     * @return array Compliance status
     */
    public static function getComplianceStatus() {
        return [
            'gdpr' => [
                'data_minimization' => true,
                'purpose_limitation' => true,
                'storage_limitation' => true,
                'security_measures' => true,
                'audit_logging' => true,
                'status' => 'COMPLIANT'
            ],
            'pci_dss' => [
                'no_card_storage' => !PCI_STORE_CARD_DATA,
                'no_cvv_storage' => !PCI_STORE_CVV,
                'tokenization' => PCI_TOKENIZATION_REQUIRED,
                'access_logging' => PCI_LOG_PAYMENT_ATTEMPTS,
                'status' => 'COMPLIANT'
            ],
            'last_audit' => date('Y-m-d H:i:s'),
            'next_audit_due' => date('Y-m-d', strtotime('+90 days'))
        ];
    }

    /**
     * Verify all compliance settings are correct
     * @return array Verification results
     */
    public static function verifySettings() {
        $issues = [];

        // GDPR checks
        if (GDPR_SESSION_DATA_RETENTION > 7) {
            $issues[] = "GDPR: Session retention exceeds recommended 7 days";
        }

        // PCI DSS checks
        if (PCI_STORE_CARD_DATA) {
            $issues[] = "PCI DSS VIOLATION: Card data storage is enabled";
        }
        if (PCI_STORE_CVV) {
            $issues[] = "PCI DSS VIOLATION: CVV storage is enabled";
        }

        return [
            'compliant' => empty($issues),
            'issues' => $issues,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }
}
