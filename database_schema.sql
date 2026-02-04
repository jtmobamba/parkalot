-- ============================================
-- ParkaLot Enhanced Database Schema
-- ============================================

-- Drop existing tables if needed (for clean setup)
-- DROP TABLE IF EXISTS activity_logs;
-- DROP TABLE IF EXISTS job_applications;
-- DROP TABLE IF EXISTS email_verifications;
-- DROP TABLE IF EXISTS payments;
-- DROP TABLE IF EXISTS employee_contracts;
-- DROP TABLE IF EXISTS reservations;
-- DROP TABLE IF EXISTS garages;
-- DROP TABLE IF EXISTS users;

-- ============================================
-- 1. USERS TABLE (with role-based access)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer', 'employee', 'senior_employee', 'manager') DEFAULT 'customer',
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. GARAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS garages (
    garage_id INT AUTO_INCREMENT PRIMARY KEY,
    garage_name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    total_spaces INT NOT NULL,
    price_per_hour DECIMAL(10, 2) NOT NULL,
    rating DECIMAL(3, 2) DEFAULT 0.00,
    amenities TEXT,
    image_url VARCHAR(500),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (location),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. RESERVATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    garage_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'completed', 'cancelled', 'refunded') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (garage_id) REFERENCES garages(garage_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_garage_id (garage_id),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. PAYMENTS TABLE (Stripe Integration)
-- ============================================
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GBP',
    stripe_payment_intent_id VARCHAR(255),
    stripe_charge_id VARCHAR(255),
    payment_status ENUM('pending', 'succeeded', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. EMPLOYEE CONTRACTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS employee_contracts (
    contract_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_type ENUM('employee', 'senior_employee', 'manager') DEFAULT 'employee',
    department VARCHAR(100),
    position VARCHAR(100),
    salary DECIMAL(10, 2) DEFAULT 0.00,
    hire_date DATE DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    contract_status ENUM('active', 'terminated', 'suspended', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_contract_status (contract_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 6. JOB APPLICATIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS job_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    position_applied VARCHAR(100) NOT NULL,
    cv_file_path VARCHAR(500),
    cover_letter TEXT,
    status ENUM('pending', 'reviewing', 'interviewed', 'accepted', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_position (position_applied)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. EMAIL VERIFICATIONS TABLE (OTP)
-- ============================================
CREATE TABLE IF NOT EXISTS email_verifications (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_otp_code (otp_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. ACTIVITY LOGS TABLE (Time-based tracking)
-- ============================================
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. USER PREFERENCES TABLE (For AI Recommendations & Employee Management)
-- ============================================
CREATE TABLE IF NOT EXISTS user_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preferred_location VARCHAR(255),
    max_price_per_hour DECIMAL(10, 2),
    preferred_amenities TEXT,
    -- Employee management fields
    max_hours INT DEFAULT NULL COMMENT 'Maximum working hours per day',
    operation_status ENUM('completed', 'incomplete', 'breaktime') DEFAULT 'incomplete' COMMENT 'Current operation status',
    rating INT DEFAULT NULL COMMENT 'Performance rating 1-5',
    current_shift VARCHAR(50) DEFAULT 'Day Shift' COMMENT 'Current shift assignment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. GARAGE REVIEWS TABLE (For AI ratings)
-- ============================================
CREATE TABLE IF NOT EXISTS garage_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    garage_id INT NOT NULL,
    reservation_id INT,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (garage_id) REFERENCES garages(garage_id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE SET NULL,
    INDEX idx_garage_id (garage_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- SAMPLE DATA FOR TESTING
-- ============================================

-- Insert sample garages
INSERT INTO garages (garage_name, location, total_spaces, price_per_hour, rating, amenities, latitude, longitude) VALUES
('City Center Garage', 'Manchester City Center', 100, 5.50, 4.5, 'CCTV,24/7 Access,EV Charging', 53.4808, -2.2426),
('Airport Parking', 'Manchester Airport', 500, 3.00, 4.2, 'Shuttle Service,Covered Parking', 53.3539, -2.2750),
('Suburban Safe Park', 'Stockport', 50, 2.50, 4.8, 'Security Guard,Well Lit', 53.4106, -2.1575),
('Business District Garage', 'Salford Quays', 200, 6.00, 4.3, 'CCTV,EV Charging,Car Wash', 53.4723, -2.2930),
('Budget Parking Lot', 'Oldham', 75, 1.50, 3.9, 'Basic Security,Outdoor', 53.5444, -2.1169);

-- Insert sample manager user (password: Manager123!)
INSERT INTO users (full_name, email, password_hash, role, email_verified) VALUES
('Admin Manager', 'manager@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', TRUE);

-- Insert sample employees
INSERT INTO users (full_name, email, password_hash, role, email_verified) VALUES
('John Smith', 'employee@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', TRUE),
('Sarah Johnson', 'senior@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'senior_employee', TRUE);

-- Insert employee contracts
INSERT INTO employee_contracts (user_id, employee_type, department, position, salary, hire_date, start_date, contract_status) VALUES
(1, 'manager', 'management', 'General Manager', 55000.00, '2023-01-01', '2023-01-01', 'active'),
(2, 'employee', 'operations', 'Parking Attendant', 25000.00, '2023-06-01', '2023-06-01', 'active'),
(3, 'senior_employee', 'customer_service', 'Senior Customer Service Rep', 35000.00, '2023-03-01', '2023-03-01', 'active');

-- Insert user preferences for employees
INSERT INTO user_preferences (user_id, operation_status, current_shift) VALUES
(1, 'completed', 'Day Shift'),
(2, 'incomplete', 'Morning Shift'),
(3, 'completed', 'Day Shift');
