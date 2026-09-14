# StockTrack Operations Guide

## Database configuration

For local XAMPP development, StockTrack falls back to the local MySQL defaults. Before deployment, configure these environment variables and use a dedicated MySQL account:

```text
STOCKTRACK_DB_HOST=localhost
STOCKTRACK_DB_USER=stocktrack_app
STOCKTRACK_DB_PASS=replace-with-a-strong-password
STOCKTRACK_DB_NAME=stocktrack
```

Do not use the MySQL `root` account or commit production credentials to the project.

## Database backup

Create a daily SQL backup with the MySQL client:

```powershell
& 'C:\xampp\mysql\bin\mysqldump.exe' -u stocktrack_app -p stocktrack > 'C:\backups\stocktrack\stocktrack-$(Get-Date -Format yyyyMMdd-HHmmss).sql'
```

Keep multiple backup generations and periodically restore one into a test database. A backup is only useful after a restore has been verified.

## Applying migrations

For a fresh database, import the current root `stocktrack.sql` once. For an existing database created from an older StockTrack schema, back it up first and import the single upgrade bundle below. Pulling the code alone does not update an existing database.

```powershell
Get-Content migrations\complete_upgrade.sql -Raw | & 'C:\xampp\mysql\bin\mysql.exe' -u stocktrack_app -p stocktrack
```

Do not run the upgrade bundle more than once against the same database.

For a quick RIPE schema check:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u stocktrack_app -p stocktrack -e "SHOW COLUMNS FROM items LIKE 'property_ics_number'; SHOW COLUMNS FROM items LIKE 'unit_value'; SHOW COLUMNS FROM items LIKE 'balance_per_card';"
```

## Acceptance test checklist

- Log in with each role and confirm permitted and forbidden pages.
- Submit a form with a missing or invalid CSRF token and confirm HTTP 403.
- Confirm inventory, user, and category deletion require POST confirmation.
- Attempt five invalid logins and confirm throttling activates.
- Confirm a new user receives a one-time temporary password and is forced to change it.
- Attempt to borrow or use more than available stock and confirm rejection.
- Delete an item with logbook history and confirm the history remains as `Deleted item`.
- Generate reports with valid and invalid monthly/yearly values.
- Verify inventory pagination retains search and filter parameters.
- Restore a database backup into a clean test database and verify application login.

## Deployment checklist

- Enable HTTPS.
- Set `STOCKTRACK_DB_*` environment variables.
- Create a least-privilege MySQL user.
- Change the bundled administrator password immediately.
- Configure scheduled backups.
- Disable displaying PHP errors to users and review the PHP error log.
- Restrict access to the application and database backup directories.
