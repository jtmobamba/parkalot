-- ParkaLot System - All Missing Tables
-- Run this SQL in phpMyAdmin to add all required tables

-- =============================================
-- REPORTS TABLE (for Senior Employee Dashboard)
-- =============================================
CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    department VARCHAR(100),
    employee_id INT DEFAULT NULL,
    report_type ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_author (author_id),
    INDEX idx_department (department),
    INDEX idx_report_type (report_type),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- PASSWORD RESETS TABLE (for Forgot Password)
-- =============================================
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

-- =============================================
-- Verify tables were created
-- =============================================
SELECT 'Tables created successfully!' AS status;
SHOW TABLES LIKE 'reports';
SHOW TABLES LIKE 'password_resets';
