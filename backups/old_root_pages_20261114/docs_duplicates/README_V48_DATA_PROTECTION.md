# 5S SMART V48 - Data Protection

- The live database remains `db/database.sqlite`.
- V48 does not replace the live database with a blank database.
- Before application writes, `config.php` creates at most one automatic backup per hour in `backups/auto_YYYYMMDD_HH.sqlite`.
- SQLite WAL is checkpointed before the backup copy.
- Restoring through `backup.php` first creates a `before_restore_YYYYMMDD_HHMMSS.sqlite` backup.
- A manual backup is still available from `backup.php`.
- The existing data in `db/database.sqlite` is preserved.

## Important
Do not delete `db/database.sqlite`. If moving the application to another XAMPP folder, copy the whole project folder including `db/`.
