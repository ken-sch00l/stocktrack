-- Store the reusable report header and signature configuration.
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
