-- Password Reset Table for ParkaLot System
-- Run this SQL to add password reset functionality

CREATE TABLE IF NOT EXISTS password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_otp (otp_code),
    INDEX idx_expires (expires_at),

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clean up expired/used tokens periodically (optional event)
-- CREATE EVENT IF NOT EXISTS cleanup_password_resets
-- ON SCHEDULE EVERY 1 HOUR
-- DO DELETE FROM password_resets WHERE expires_at < NOW() OR used = TRUE;
