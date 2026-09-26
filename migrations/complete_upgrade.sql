-- StockTrack complete upgrade for existing databases.
--
-- Use this single file when upgrading a database created from the original
-- stocktrack.sql schema. Do not run it against a fresh database: import the
-- current root stocktrack.sql instead.
--
-- This bundle preserves the numbered migration order 002 through 009.

-- 002: Preserve logbook history when an item is deleted.
SET @logbook_item_fk = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'logbook'
      AND COLUMN_NAME = 'item_id'
      AND REFERENCED_TABLE_NAME = 'items'
    LIMIT 1
);

SET @drop_fk_sql = IF(
    @logbook_item_fk IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE logbook DROP FOREIGN KEY `', @logbook_item_fk, '`')
);

PREPARE drop_fk_statement FROM @drop_fk_sql;
EXECUTE drop_fk_statement;
DEALLOCATE PREPARE drop_fk_statement;

ALTER TABLE logbook MODIFY item_id INT NULL;

ALTER TABLE logbook
    ADD CONSTRAINT fk_logbook_item
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE SET NULL;

-- 003: Add first-login password change tracking.
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'must_change_password'
);

SET @add_column_sql = IF(
    @column_exists = 0,
    'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password',
    'SELECT 1'
);

PREPARE add_column_statement FROM @add_column_sql;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

UPDATE users
SET must_change_password = 1
WHERE username = 'admin';

-- 004: Add login throttling and audit logging.
CREATE TABLE IF NOT EXISTS login_attempts (
    identifier VARCHAR(190) PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 0,
    window_started DATETIME NOT NULL,
    blocked_until DATETIME NULL
);

CREATE TABLE IF NOT EXISTS audit_log (
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

-- 005: Add notifications.
CREATE TABLE IF NOT EXISTS notifications (
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

-- 006: Link returns to borrowing transactions.
ALTER TABLE logbook
    ADD COLUMN return_for_log_id INT NULL AFTER item_id;

ALTER TABLE logbook
    ADD CONSTRAINT fk_logbook_return_for
    FOREIGN KEY (return_for_log_id) REFERENCES logbook(log_id) ON DELETE SET NULL;

-- 007: Add the super administrator role.
ALTER TABLE users
    MODIFY role ENUM('super_admin', 'admin', 'secretary', 'treasurer', 'committee') NOT NULL;

UPDATE users
SET role = 'super_admin'
WHERE username = 'admin';

-- 008: Replace PAR with RIPE for new reports while preserving old PAR history.
ALTER TABLE reports_log
    MODIFY report_type ENUM('RIS', 'ICS', 'RIPE', 'PAR') NOT NULL;

-- 009: Add RIPE inventory fields.
ALTER TABLE items
    ADD COLUMN property_ics_number VARCHAR(100) NULL AFTER tracking_number,
    ADD COLUMN coverage_type ENUM('PAR', 'ICS') NOT NULL DEFAULT 'ICS' AFTER property_ics_number,
    ADD COLUMN date_acquired DATE NULL AFTER date_purchased,
    ADD COLUMN unit_measure VARCHAR(50) NULL AFTER date_acquired,
    ADD COLUMN unit_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_measure,
    ADD COLUMN balance_per_card INT NOT NULL DEFAULT 1 AFTER unit_value,
    ADD COLUMN on_hand_per_count INT NOT NULL DEFAULT 1 AFTER balance_per_card,
    ADD COLUMN shortage_overage_qty INT NOT NULL DEFAULT 0 AFTER on_hand_per_count,
    ADD COLUMN shortage_overage_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER shortage_overage_qty,
    ADD COLUMN remarks TEXT NULL AFTER shortage_overage_value;

UPDATE items
SET property_ics_number = tracking_number,
    date_acquired = date_purchased,
    unit_measure = unit,
    balance_per_card = quantity,
    on_hand_per_count = quantity,
    shortage_overage_qty = 0,
    shortage_overage_value = 0.00,
    remarks = notes
WHERE property_ics_number IS NULL;

-- 010: Add reusable report header settings.
CREATE TABLE IF NOT EXISTS report_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO report_settings (setting_key, setting_value) VALUES
('header_line_1', 'Republic of the Philippines'),
('header_line_2', 'Province of Benguet'),
('header_line_3', 'Municipality of La Trinidad'),
('header_line_4', 'Barangay Puguis'),
('fund_cluster', 'GENERAL FUND'),
('accountable_person', ''),
('accountable_position', ''),
('assumption_date', ''),
('report_place', 'Barangay Puguis, La Trinidad, Benguet'),
('prepared_by_name', ''),
('prepared_by_position', ''),
('certified_by_name', ''),
('certified_by_position', ''),
('logo_path', ''),
('logo_x', '50'),
('logo_y', '8'),
('logo_width', '72'),
('logo_height', '72')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- 011: Add acquisition-source metadata to item records.
ALTER TABLE items
    ADD COLUMN acquisition_type ENUM('Purchased', 'Donated', 'Other') NOT NULL DEFAULT 'Purchased',
    ADD COLUMN donor_name_organization VARCHAR(150) NULL,
    ADD COLUMN donor_office_department VARCHAR(150) NULL;

UPDATE items
SET acquisition_type = 'Purchased'
WHERE acquisition_type IS NULL;

UPDATE items
SET donor_name_organization = NULL
WHERE donor_name_organization = '';

UPDATE items
SET donor_office_department = NULL
WHERE donor_office_department = '';

SELECT 'StockTrack upgrade complete. Verify the items, users, logbook, notifications, audit_log, and reports_log tables.' AS status;
