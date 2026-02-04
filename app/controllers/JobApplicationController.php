<?php
/**
 * JobApplicationController - Handles job applications and CV uploads
 */
class JobApplicationController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Submit a new job application
     */
    public function submitApplication($data, $file) {
        try {
            // Validate required fields
            if (empty($data['full_name']) || empty($data['email']) || empty($data['position_applied'])) {
                return ['error' => 'Missing required fields'];
            }

            // Handle CV file upload
            $cvFilePath = null;
            if ($file && isset($file['cv_file']) && $file['cv_file']['error'] === UPLOAD_ERR_OK) {
                $cvFilePath = $this->handleFileUpload($file['cv_file']);

                if (!$cvFilePath) {
                    return ['error' => 'Failed to upload CV file'];
                }
            }

            // Build user preferences JSON
            $userPreferences = json_encode([
                'preferred_location' => $data['preferred_location'] ?? null,
                'max_price_per_hour' => !empty($data['max_price_per_hour']) ? floatval($data['max_price_per_hour']) : null,
                'preferred_amenities' => $data['preferred_amenities'] ?? null
            ]);

            // Combine cover letter with preferences for storage
            $coverLetterWithPrefs = $data['cover_letter'] ?? '';
            if (!empty($coverLetterWithPrefs)) {
                $coverLetterWithPrefs .= "\n\n---\n";
            }
            $coverLetterWithPrefs .= "[USER_PREFERENCES:" . $userPreferences . "]";

            // Insert application into database
            $stmt = $this->db->prepare("
                INSERT INTO job_applications
                (full_name, email, phone, position_applied, cv_file_path, cover_letter, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");

            $result = $stmt->execute([
                $data['full_name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['position_applied'],
                $cvFilePath,
                $coverLetterWithPrefs
            ]);

            if ($result) {
                $applicationId = $this->db->lastInsertId();

                return [
                    'success' => true,
                    'message' => 'Application submitted successfully',
                    'application_id' => $applicationId,
                    'preferences_saved' => true
                ];
            }

            return ['error' => 'Failed to submit application'];

        } catch (Exception $e) {
            return ['error' => 'Server error: ' . $e->getMessage()];
        }
    }

    /**
     * Handle CV file upload
     */
    private function handleFileUpload($file) {
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/../../uploads/cv/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate file type
        $allowedTypes = ['application/pdf', 'application/msword', 
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            return false;
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return false;
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'cv_' . uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return 'uploads/cv/' . $filename;
        }

        return false;
    }

    /**
     * Get all job applications (Manager only)
     */
    public function getAllApplications() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    application_id,
                    full_name,
                    email,
                    phone,
                    position_applied,
                    status,
                    applied_at,
                    reviewed_at
                FROM job_applications
                ORDER BY applied_at DESC
            ");
            
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'applications' => $applications,
                'count' => count($applications)
            ];

        } catch (Exception $e) {
            return ['error' => 'Failed to fetch applications'];
        }
    }

    /**
     * Get single application with CV download link
     */
    public function getApplication($applicationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM job_applications WHERE application_id = ?
            ");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($application) {
                return [
                    'success' => true,
                    'application' => $application
                ];
            }

            return ['error' => 'Application not found'];

        } catch (Exception $e) {
            return ['error' => 'Failed to fetch application'];
        }
    }

    /**
     * Update application status
     */
    public function updateApplicationStatus($applicationId, $status, $reviewerId) {
        try {
            $validStatuses = ['pending', 'reviewing', 'interviewed', 'accepted', 'rejected'];

            if (!in_array($status, $validStatuses)) {
                return ['error' => 'Invalid status'];
            }

            $stmt = $this->db->prepare("
                UPDATE job_applications
                SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE application_id = ?
            ");

            $result = $stmt->execute([$status, $reviewerId, $applicationId]);

            if ($result) {
                // If accepted, create user account and preferences
                if ($status === 'accepted') {
                    $this->createEmployeeFromApplication($applicationId);
                }

                return [
                    'success' => true,
                    'message' => 'Application status updated'
                ];
            }

            return ['error' => 'Failed to update status'];

        } catch (Exception $e) {
            return ['error' => 'Server error'];
        }
    }

    /**
     * Extract user preferences from cover letter
     */
    private function extractPreferences($coverLetter) {
        $preferences = [
            'preferred_location' => null,
            'max_price_per_hour' => null,
            'preferred_amenities' => null
        ];

        if (preg_match('/\[USER_PREFERENCES:(.*?)\]/', $coverLetter, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded) {
                $preferences = array_merge($preferences, $decoded);
            }
        }

        return $preferences;
    }

    /**
     * Create employee user account from accepted application
     */
    private function createEmployeeFromApplication($applicationId) {
        try {
            // Get application details
            $stmt = $this->db->prepare("SELECT * FROM job_applications WHERE application_id = ?");
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                return false;
            }

            // Check if user already exists
            $checkStmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
            $checkStmt->execute([$application['email']]);
            if ($checkStmt->fetch()) {
                return false; // User already exists
            }

            // Determine role based on position
            $role = 'employee';
            $seniorPositions = ['Senior Customer Service Representative', 'Facility Manager', 'Software Developer'];
            if (in_array($application['position_applied'], $seniorPositions)) {
                $role = 'senior_employee';
            }

            // Generate temporary password
            $tempPassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

            // Create user account
            $userStmt = $this->db->prepare("
                INSERT INTO users (full_name, email, password_hash, role, email_verified)
                VALUES (?, ?, ?, ?, FALSE)
            ");
            $userStmt->execute([
                $application['full_name'],
                $application['email'],
                $passwordHash,
                $role
            ]);

            $userId = $this->db->lastInsertId();

            // Extract and save user preferences
            $preferences = $this->extractPreferences($application['cover_letter'] ?? '');

            if ($preferences['preferred_location'] || $preferences['max_price_per_hour'] || $preferences['preferred_amenities']) {
                $prefStmt = $this->db->prepare("
                    INSERT INTO user_preferences (user_id, preferred_location, max_price_per_hour, preferred_amenities)
                    VALUES (?, ?, ?, ?)
                ");
                $prefStmt->execute([
                    $userId,
                    $preferences['preferred_location'],
                    $preferences['max_price_per_hour'],
                    $preferences['preferred_amenities']
                ]);
            }

            // Create employee contract
            $contractStmt = $this->db->prepare("
                INSERT INTO employee_contracts (user_id, employee_type, position, hire_date, contract_start_date, status)
                VALUES (?, ?, ?, CURDATE(), CURDATE(), 'active')
            ");
            $contractStmt->execute([
                $userId,
                $role,
                $application['position_applied']
            ]);

            return [
                'user_id' => $userId,
                'temp_password' => $tempPassword,
                'role' => $role
            ];

        } catch (Exception $e) {
            error_log("Failed to create employee from application: " . $e->getMessage());
            return false;
        }
    }
}
?>
