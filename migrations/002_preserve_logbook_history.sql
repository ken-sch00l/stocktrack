-- Preserve logbook history when an inventory item is deleted.
-- Run this once against an existing StockTrack database.

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
