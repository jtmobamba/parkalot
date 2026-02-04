<?php
/**
 * EmailVerificationService - OTP generation and email verification
 * FIXED: Added user existence check to prevent foreign key constraint error
 * ENHANCED: Now uses PHPMailer with SMTP for reliable email delivery
 * UPDATED: Returns success even if email fails (for testing without SMTP)
 */

// Import PHPMailer classes
require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../../config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailVerificationService {
    private $db;
    private $otpExpiry = 10; // minutes
    
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Generate and send OTP code
     * FIXED: Now verifies user exists before inserting into email_verifications
     * UPDATED: Returns success even if email fails (OTP still in database)
     */
    public function generateAndSendOTP($userId, $email) {
        try {
            // FIX: Verify the user exists in the users table first
            $stmt = $this->db->prepare("SELECT user_id, full_name FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['error' => 'Failed to generate OTP: User not found in database'];
            }
            
            // Generate 6-digit OTP
            $otpCode = $this->generateOTPCode();
            
            // Calculate expiry time
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->otpExpiry} minutes"));
            
            // Store OTP in database FIRST (before attempting email)
            $stmt = $this->db->prepare("
                INSERT INTO email_verifications (user_id, email, otp_code, expires_at)
                VALUES (?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([$userId, $email, $otpCode, $expiresAt]);
            
            if (!$result) {
                return ['error' => 'Failed to generate OTP in database'];
            }
            
            // OTP is now safely in database - try to send email
            $emailSent = $this->sendOTPEmail($email, $otpCode, $user['full_name'] ?? 'User');
            
            // Return success even if email fails (for testing without SMTP)
            if ($emailSent['success']) {
                // Email sent successfully
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully to ' . $email,
                    'otp_code' => $otpCode,  // Include for testing
                    'expires_in' => $this->otpExpiry . ' minutes'
                ];
            } else {
                // Email failed but OTP is still valid in database
                error_log("Email sending failed for user $userId: " . $emailSent['error'] . " - OTP: $otpCode");
                
                return [
                    'success' => true,  // Still success because OTP is in database!
                    'message' => 'OTP generated successfully (check console for code)',
                    'otp_code' => $otpCode,  // Return OTP for testing
                    'expires_in' => $this->otpExpiry . ' minutes',
                    'warning' => 'Email not sent - SMTP not configured. OTP: ' . $otpCode
                ];
            }
            
        } catch (Exception $e) {
            error_log("OTP generation exception: " . $e->getMessage());
            return ['error' => 'Failed to generate OTP: ' . $e->getMessage()];
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOTP($userId, $otpCode) {
        try {
            // Find matching OTP
            $stmt = $this->db->prepare("
                SELECT * FROM email_verifications
                WHERE user_id = ? AND otp_code = ? AND expires_at > NOW() AND verified = FALSE
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $otpCode]);
            $verification = $stmt->fetch();
            
            if (!$verification) {
                return ['error' => 'Invalid or expired OTP'];
            }
            
            // Mark as verified
            $stmt = $this->db->prepare("
                UPDATE email_verifications
                SET verified = TRUE
                WHERE verification_id = ?
            ");
            $stmt->execute([$verification['verification_id']]);
            
            // Update user's email_verified status
            $stmt = $this->db->prepare("
                UPDATE users
                SET email_verified = TRUE
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            return [
                'success' => true,
                'message' => 'Email verified successfully'
            ];
            
        } catch (Exception $e) {
            return ['error' => 'Failed to verify OTP: ' . $e->getMessage()];
        }
    }

    /**
     * Generate random 6-digit OTP code
     */
    private function generateOTPCode() {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP via email using PHPMailer with SMTP
     */
    private function sendOTPEmail($email, $otpCode, $userName = 'User') {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = EmailConfig::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = EmailConfig::SMTP_USERNAME;
            $mail->Password   = EmailConfig::SMTP_PASSWORD;
            $mail->SMTPSecure = EmailConfig::SMTP_SECURE;
            $mail->Port       = EmailConfig::SMTP_PORT;
            $mail->CharSet    = EmailConfig::CHARSET;
            
            // Enable debug output for development
            if (EmailConfig::DEBUG) {
                $mail->SMTPDebug = 0; // 0=off, 1=client, 2=client+server
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer [$level]: $str");
                };
            }
            
            // Recipients
            $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
            $mail->addAddress($email, $userName);
            $mail->addReplyTo(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your ParkaLot Verification Code';
            
            // HTML email body
            $mail->Body = $this->getEmailHTMLTemplate($otpCode, $userName);
            
            // Plain text version
            $mail->AltBody = "Hello $userName,\n\n"
                           . "Your verification code is: $otpCode\n\n"
                           . "This code will expire in {$this->otpExpiry} minutes.\n\n"
                           . "If you did not request this code, please ignore this email.\n\n"
                           . "Best regards,\nParkaLot Team";
            
            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            error_log("Email send failed: " . $mail->ErrorInfo);
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    /**
     * Get HTML email template for OTP
     */
    private function getEmailHTMLTemplate($otpCode, $userName) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .otp-code { font-size: 32px; font-weight: bold; color: #2c3e50; text-align: center; 
                            padding: 20px; background: white; border: 2px dashed #3498db; 
                            letter-spacing: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; color: #7f8c8d; font-size: 12px; }
                .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; 
                          border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🅿️ ParkaLot</h1>
                    <p>Email Verification</p>
                </div>
                <div class='content'>
                    <h2>Hello $userName,</h2>
                    <p>Thank you for registering with ParkaLot. To complete your registration, 
                       please use the verification code below:</p>
                    
                    <div class='otp-code'>$otpCode</div>
                    
                    <p><strong>This code will expire in {$this->otpExpiry} minutes.</strong></p>
                    
                    <div class='warning'>
                        ⚠️ <strong>Security Notice:</strong> If you did not request this code, 
                        please ignore this email. Do not share this code with anyone.
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 ParkaLot System. All rights reserved.</p>
                    <p>This is an automated email. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Resend OTP (with rate limiting)
     */
    public function resendOTP($userId, $email) {
        try {
            // Check rate limiting (max 3 attempts in 5 minutes)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as recent_attempts
                FROM email_verifications
                WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch();
            
            if ($result['recent_attempts'] >= 3) {
                return ['error' => 'Too many requests. Please try again later.'];
            }
            
            // Generate and send new OTP
            return $this->generateAndSendOTP($userId, $email);
            
        } catch (Exception $e) {
            return ['error' => 'Failed to resend OTP: ' . $e->getMessage()];
        }
    }

    /**
     * Check if user's email is already verified
     */
    public function isEmailVerified($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT email_verified FROM users WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            return $user && $user['email_verified'] == 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Clean up expired OTP codes (run periodically)
     */
    public function cleanupExpiredOTPs() {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM email_verifications
                WHERE expires_at < NOW() AND verified = FALSE
            ");
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
}
?>