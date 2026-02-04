-- ParkaLot Manager Dashboard Schema Updates
-- Run this SQL to add the necessary tables and columns for the manager/senior employee dashboard features

-- ============================================
-- UPDATE USER_PREFERENCES TABLE
-- ============================================
-- Add columns if they don't exist
ALTER TABLE user_preferences
ADD COLUMN IF NOT EXISTS max_hours INT DEFAULT NULL COMMENT 'Maximum working hours per day',
ADD COLUMN IF NOT EXISTS operation_status ENUM('completed', 'incomplete', 'breaktime') DEFAULT 'incomplete' COMMENT 'Current operation status',
ADD COLUMN IF NOT EXISTS rating INT DEFAULT NULL COMMENT 'Performance rating 1-5',
ADD COLUMN IF NOT EXISTS current_shift VARCHAR(50) DEFAULT 'Day Shift' COMMENT 'Current shift assignment';

-- If the above fails (MySQL version doesn't support IF NOT EXISTS for columns), use this instead:
-- ALTER TABLE user_preferences ADD COLUMN max_hours INT DEFAULT NULL;
-- ALTER TABLE user_preferences ADD COLUMN operation_status ENUM('completed', 'incomplete', 'breaktime') DEFAULT 'incomplete';
-- ALTER TABLE user_preferences ADD COLUMN rating INT DEFAULT NULL;
-- ALTER TABLE user_preferences ADD COLUMN current_shift VARCHAR(50) DEFAULT 'Day Shift';

-- Valid shift values:
-- 'Day Shift', 'Morning Shift', 'Afternoon Shift', 'Evening Shift',
-- 'Night Shift', 'Evening/Night Shift', 'Rotating Shift', 'Weekend Shift', 'On Call'

-- ============================================
-- UPDATE VEHICLES TABLE FOR DVLA API DATA
-- ============================================
-- Add columns for additional DVLA API data
ALTER TABLE vehicles
ADD COLUMN IF NOT EXISTS fuel_type VARCHAR(50) DEFAULT NULL COMMENT 'Fuel type (petrol, diesel, electric, hybrid)',
ADD COLUMN IF NOT EXISTS engine_capacity INT DEFAULT NULL COMMENT 'Engine capacity in cc',
ADD COLUMN IF NOT EXISTS co2_emissions INT DEFAULT NULL COMMENT 'CO2 emissions in g/km',
ADD COLUMN IF NOT EXISTS first_registered DATE DEFAULT NULL COMMENT 'Date first registered',
ADD COLUMN IF NOT EXISTS date_of_last_v5c DATE DEFAULT NULL COMMENT 'Date of last V5C issued',
ADD COLUMN IF NOT EXISTS wheel_plan VARCHAR(20) DEFAULT NULL COMMENT 'Wheel plan (e.g., 2 AXLE RIGID BODY)',
ADD COLUMN IF NOT EXISTS revenue_weight INT DEFAULT NULL COMMENT 'Revenue weight in kg',
ADD COLUMN IF NOT EXISTS type_approval VARCHAR(50) DEFAULT NULL COMMENT 'Type approval category',
ADD COLUMN IF NOT EXISTS euro_status VARCHAR(20) DEFAULT NULL COMMENT 'Euro emissions status (e.g., EURO 6)',
ADD COLUMN IF NOT EXISTS real_driving_emissions VARCHAR(10) DEFAULT NULL COMMENT 'Real driving emissions',
ADD COLUMN IF NOT EXISTS exported BOOLEAN DEFAULT FALSE COMMENT 'Whether vehicle has been exported',
ADD COLUMN IF NOT EXISTS scrapped BOOLEAN DEFAULT FALSE COMMENT 'Whether vehicle has been scrapped',
ADD COLUMN IF NOT EXISTS api_last_updated TIMESTAMP DEFAULT NULL COMMENT 'Last time data was fetched from DVLA API';

-- If the above fails (MySQL version doesn't support IF NOT EXISTS for columns), use:
-- ALTER TABLE vehicles ADD COLUMN fuel_type VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE vehicles ADD COLUMN engine_capacity INT DEFAULT NULL;
-- ALTER TABLE vehicles ADD COLUMN co2_emissions INT DEFAULT NULL;
-- ALTER TABLE vehicles ADD COLUMN first_registered DATE DEFAULT NULL;
-- ALTER TABLE vehicles ADD COLUMN euro_status VARCHAR(20) DEFAULT NULL;
-- ALTER TABLE vehicles ADD COLUMN api_last_updated TIMESTAMP DEFAULT NULL;

-- ============================================
-- CREATE REPORTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    author_id INT NOT NULL COMMENT 'User ID of the report author',
    department VARCHAR(50) COMMENT 'Department the report relates to',
    employee_id INT DEFAULT NULL COMMENT 'Specific employee the report is about (optional)',
    report_type ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL COMMENT 'Report content - up to 100,000 characters',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(user_id) ON DELETE SET NULL,

    INDEX idx_author (author_id),
    INDEX idx_department (department),
    INDEX idx_report_type (report_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- UPDATE EMPLOYEE_CONTRACTS TABLE
-- ============================================
-- Ensure department and position columns exist with proper values
ALTER TABLE employee_contracts
MODIFY COLUMN department VARCHAR(50) DEFAULT NULL COMMENT 'management, operations, customer_service, software_systems',
MODIFY COLUMN position VARCHAR(100) DEFAULT NULL COMMENT 'Employee position title';

-- Fix column names if they don't match API expectations
-- If you have 'status' column, rename it to 'contract_status'
-- ALTER TABLE employee_contracts CHANGE COLUMN status contract_status ENUM('active', 'terminated', 'suspended', 'pending') DEFAULT 'active';

-- If you have 'contract_start_date', rename to 'start_date'
-- ALTER TABLE employee_contracts CHANGE COLUMN contract_start_date start_date DATE DEFAULT NULL;

-- If you have 'contract_end_date', rename to 'end_date'
-- ALTER TABLE employee_contracts CHANGE COLUMN contract_end_date end_date DATE DEFAULT NULL;

-- Add contract_status column if it doesn't exist
ALTER TABLE employee_contracts
ADD COLUMN IF NOT EXISTS contract_status ENUM('active', 'terminated', 'suspended', 'pending') DEFAULT 'active';

-- Add start_date column if it doesn't exist
ALTER TABLE employee_contracts
ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT NULL;

-- Add end_date column if it doesn't exist
ALTER TABLE employee_contracts
ADD COLUMN IF NOT EXISTS end_date DATE DEFAULT NULL;

-- Make employee_type nullable with default
ALTER TABLE employee_contracts
MODIFY COLUMN employee_type ENUM('employee', 'senior_employee', 'manager') DEFAULT 'employee';

-- Make hire_date nullable
ALTER TABLE employee_contracts
MODIFY COLUMN hire_date DATE DEFAULT NULL;

-- ============================================
-- ADD STATUS COLUMN TO JOB_APPLICATIONS IF MISSING
-- ============================================
ALTER TABLE job_applications
ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';

-- ============================================
-- SAMPLE DATA FOR TESTING
-- ============================================

-- Insert sample user_preferences if not exist
INSERT INTO user_preferences (user_id, operation_status, max_hours, rating)
SELECT u.user_id,
       ELT(FLOOR(1 + RAND() * 3), 'completed', 'incomplete', 'breaktime'),
       FLOOR(4 + RAND() * 5),
       FLOOR(1 + RAND() * 5)
FROM users u
LEFT JOIN user_preferences up ON u.user_id = up.user_id
WHERE up.user_id IS NULL AND u.role IN ('employee', 'senior_employee')
LIMIT 10;

-- Insert sample reports
INSERT INTO reports (author_id, department, report_type, title, content)
SELECT u.user_id, ec.department, 'daily', 'Daily Operations Report',
       'This is a sample daily report for testing purposes. All operations are running smoothly.'
FROM users u
JOIN employee_contracts ec ON u.user_id = ec.user_id
WHERE u.role = 'senior_employee'
LIMIT 1;

-- ============================================
-- USEFUL QUERIES FOR DEBUGGING
-- ============================================

-- View all employees with their department and status
-- SELECT u.user_id, u.full_name, u.role, ec.department, ec.position, up.operation_status, up.rating
-- FROM users u
-- LEFT JOIN employee_contracts ec ON u.user_id = ec.user_id
-- LEFT JOIN user_preferences up ON u.user_id = up.user_id
-- WHERE u.role IN ('employee', 'senior_employee', 'manager');

-- Count tasks by status
-- SELECT operation_status, COUNT(*) as count
-- FROM user_preferences up
-- JOIN employee_contracts ec ON up.user_id = ec.user_id
-- WHERE ec.department = 'operations'
-- GROUP BY operation_status;

-- View reports by department
-- SELECT r.*, u.full_name as author_name
-- FROM reports r
-- JOIN users u ON r.author_id = u.user_id
-- ORDER BY r.created_at DESC;
