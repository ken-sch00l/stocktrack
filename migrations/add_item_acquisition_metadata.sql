SET @has_acquisition_type := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND COLUMN_NAME = 'acquisition_type'
);

SET @sql_acq := IF(@has_acquisition_type = 0,
    "ALTER TABLE items ADD COLUMN acquisition_type ENUM('Purchased', 'Donated', 'Other') NOT NULL DEFAULT 'Purchased'",
    "SELECT 1"
);
PREPARE stmt_acq FROM @sql_acq;
EXECUTE stmt_acq;
DEALLOCATE PREPARE stmt_acq;

SET @has_new_donor_name := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND COLUMN_NAME = 'donor_name_organization'
);

SET @has_legacy_donor_name := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND COLUMN_NAME = 'donor_name'
);

SET @sql_donor_name := IF(@has_new_donor_name = 0,
    IF(@has_legacy_donor_name > 0,
        "ALTER TABLE items CHANGE COLUMN donor_name donor_name_organization VARCHAR(150) NULL",
        "ALTER TABLE items ADD COLUMN donor_name_organization VARCHAR(150) NULL"
    ),
    "SELECT 1"
);
PREPARE stmt_donor_name FROM @sql_donor_name;
EXECUTE stmt_donor_name;
DEALLOCATE PREPARE stmt_donor_name;

SET @has_new_donor_office := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND COLUMN_NAME = 'donor_office_department'
);

SET @has_legacy_donor_office := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND COLUMN_NAME = 'donor_organization'
);

SET @sql_donor_office := IF(@has_new_donor_office = 0,
    IF(@has_legacy_donor_office > 0,
        "ALTER TABLE items CHANGE COLUMN donor_organization donor_office_department VARCHAR(150) NULL",
        "ALTER TABLE items ADD COLUMN donor_office_department VARCHAR(150) NULL"
    ),
    "SELECT 1"
);
PREPARE stmt_donor_office FROM @sql_donor_office;
EXECUTE stmt_donor_office;
DEALLOCATE PREPARE stmt_donor_office;

UPDATE items
SET acquisition_type = 'Purchased'
WHERE acquisition_type IS NULL;

UPDATE items
SET donor_name_organization = NULL
WHERE donor_name_organization = '';

UPDATE items
SET donor_office_department = NULL
WHERE donor_office_department = '';
