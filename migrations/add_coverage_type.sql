-- Add coverage classification for PAR and ICS property records.
ALTER TABLE items
    ADD COLUMN coverage_type ENUM('PAR', 'ICS') NOT NULL DEFAULT 'ICS' AFTER property_ics_number;

SELECT 'coverage_type migration complete.' AS status;
