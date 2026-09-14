-- Add the fields required by the RIPE inventory report.
-- Existing item data is preserved and receives sensible RIPE defaults.

ALTER TABLE items
    ADD COLUMN property_ics_number VARCHAR(100) NULL AFTER tracking_number,
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