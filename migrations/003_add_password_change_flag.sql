-- Add first-login password change tracking to existing StockTrack databases.

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

-- The bundled administrator account uses the documented default password.
-- Force it to be changed on the next login after this migration.
UPDATE users
SET must_change_password = 1
WHERE username = 'admin';
