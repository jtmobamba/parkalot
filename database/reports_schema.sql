-- Reports Table for ParkaLot System
-- Run this SQL to add the reports functionality for Senior Employees

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
