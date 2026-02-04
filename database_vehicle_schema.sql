-- ============================================
-- ParkaLot Vehicle Inspector Schema
-- Run this SQL to add vehicle inspection tables
-- ============================================

-- 1. Add 'vehicle_inspector' to the users role enum
ALTER TABLE users
MODIFY COLUMN role ENUM('customer', 'employee', 'senior_employee', 'manager', 'vehicle_inspector') DEFAULT 'customer';

-- ============================================
-- 2. VEHICLES TABLE - Stores all vehicle data
-- ============================================
CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    make VARCHAR(100),
    model VARCHAR(100),
    color VARCHAR(50),
    year INT,
    vehicle_type ENUM('car', 'motorcycle', 'van', 'truck', 'suv', 'other') DEFAULT 'car',
    owner_name VARCHAR(255),
    owner_contact VARCHAR(100),
    registration_status ENUM('valid', 'expired', 'suspended', 'unknown') DEFAULT 'unknown',
    insurance_status ENUM('valid', 'expired', 'none', 'unknown') DEFAULT 'unknown',
    mot_status ENUM('valid', 'expired', 'exempt', 'unknown') DEFAULT 'unknown',
    mot_expiry_date DATE,
    tax_status ENUM('valid', 'expired', 'exempt', 'sorn', 'unknown') DEFAULT 'unknown',
    tax_due_date DATE,
    notes TEXT,
    last_inspection_date TIMESTAMP NULL,
    inspected_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (inspected_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_license_plate (license_plate),
    INDEX idx_registration_status (registration_status),
    INDEX idx_vehicle_type (vehicle_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. USER_VEHICLES TABLE - Links users to vehicles
-- ============================================
CREATE TABLE IF NOT EXISTS user_vehicles (
    user_vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    ownership_type ENUM('owner', 'driver', 'authorized') DEFAULT 'owner',
    is_primary BOOLEAN DEFAULT FALSE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_vehicle (user_id, vehicle_id),
    INDEX idx_user_id (user_id),
    INDEX idx_vehicle_id (vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. VEHICLE_INSPECTIONS TABLE - Inspection logs
-- ============================================
CREATE TABLE IF NOT EXISTS vehicle_inspections (
    inspection_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    inspector_id INT NOT NULL,
    garage_id INT,
    inspection_type ENUM('entry', 'exit', 'routine', 'incident', 'verification') DEFAULT 'routine',
    inspection_result ENUM('pass', 'fail', 'warning', 'pending') DEFAULT 'pending',
    damage_detected BOOLEAN DEFAULT FALSE,
    damage_description TEXT,
    photo_urls TEXT,
    location_spotted VARCHAR(255),
    inspection_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (inspector_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (garage_id) REFERENCES garages(garage_id) ON DELETE SET NULL,
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_inspector_id (inspector_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. Sample Vehicle Inspector User
-- Password: Inspector123!
-- ============================================
INSERT INTO users (full_name, email, password_hash, role, email_verified) VALUES
('Vehicle Inspector', 'inspector@parkalot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vehicle_inspector', TRUE);

-- ============================================
-- 6. Sample Vehicles for Testing
-- ============================================
INSERT INTO vehicles (license_plate, make, model, color, year, vehicle_type, owner_name, registration_status, insurance_status, mot_status, tax_status) VALUES
('AB12 CDE', 'Toyota', 'Corolla', 'Silver', 2020, 'car', 'John Smith', 'valid', 'valid', 'valid', 'valid'),
('XY34 FGH', 'Ford', 'Focus', 'Blue', 2019, 'car', 'Jane Doe', 'valid', 'valid', 'expired', 'valid'),
('MN56 IJK', 'BMW', '3 Series', 'Black', 2021, 'car', 'Bob Wilson', 'valid', 'valid', 'valid', 'valid'),
('PQ78 LMN', 'Honda', 'CR-V', 'White', 2018, 'suv', 'Alice Brown', 'valid', 'expired', 'valid', 'expired'),
('RS90 OPQ', 'Mercedes', 'Sprinter', 'White', 2017, 'van', 'Delivery Co Ltd', 'valid', 'valid', 'valid', 'valid');
