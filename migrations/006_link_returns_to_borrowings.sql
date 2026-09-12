-- Link each new return to the specific borrowing transaction it settles.

ALTER TABLE logbook
    ADD COLUMN return_for_log_id INT NULL AFTER item_id;

ALTER TABLE logbook
    ADD CONSTRAINT fk_logbook_return_for
    FOREIGN KEY (return_for_log_id) REFERENCES logbook(log_id) ON DELETE SET NULL;