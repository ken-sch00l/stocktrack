-- Replace the unused PAR report option with the client's RIPE inventory report.
-- Existing PAR report history is preserved by retaining PAR in the enum.

ALTER TABLE reports_log
    MODIFY report_type ENUM('RIS', 'ICS', 'RIPE', 'PAR') NOT NULL;