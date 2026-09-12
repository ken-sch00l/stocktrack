-- StockTrack: An Inventory and Records Management System
-- Barangay Puguis, La Trinidad, Benguet
-- Database: stocktrack

CREATE DATABASE IF NOT EXISTS stocktrack;
USE stocktrack;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    role ENUM('admin', 'secretary', 'treasurer', 'committee') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE login_attempts (
    identifier VARCHAR(190) PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 0,
    window_started DATETIME NOT NULL,
    blocked_until DATETIME NULL
);

CREATE TABLE audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items table
CREATE TABLE items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(50) NOT NULL UNIQUE,
    serial_number VARCHAR(100),
    item_name VARCHAR(150) NOT NULL,
    category_id INT,
    condition_status ENUM('Serviceable', 'Unserviceable') NOT NULL DEFAULT 'Serviceable',
    quantity INT NOT NULL DEFAULT 1,
    unit VARCHAR(50),
    date_purchased DATE,
    person_in_charge VARCHAR(100),
    position VARCHAR(100),
    last_inventory_date DATE,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Logbook table
CREATE TABLE logbook (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    return_for_log_id INT NULL,
    action ENUM('Borrowed', 'Used', 'Returned') NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    borrowed_by VARCHAR(100) NOT NULL,
    purpose TEXT,
    date_action DATE NOT NULL,
    date_returned DATE,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE SET NULL,
    FOREIGN KEY (return_for_log_id) REFERENCES logbook(log_id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT NOT NULL,
    logbook_id INT NULL,
    notification_type VARCHAR(50) NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (logbook_id) REFERENCES logbook(log_id) ON DELETE SET NULL
);

-- Reports log table
CREATE TABLE reports_log (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('RIS', 'ICS', 'PAR') NOT NULL,
    period_type ENUM('Weekly', 'Monthly', 'Yearly') NOT NULL,
    period_value VARCHAR(50),
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE SET NULL
);

INSERT INTO users (full_name, username, password, role, must_change_password) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Default categories
INSERT INTO categories (category_name) VALUES
('Personal Protective Equipment (PPE)'),
('Office Supplies'),
('Equipment'),
('Furniture and Fixtures'),
('IT Equipment'),
('Infrastructure and Maintenance'),
('Other Properties');
