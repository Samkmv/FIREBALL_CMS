SET @has_two_factor_secret = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'two_factor_secret'
);
SET @migration_sql = IF(
    @has_two_factor_secret = 0,
    'ALTER TABLE users ADD COLUMN two_factor_secret TEXT NULL AFTER role',
    'SELECT 1'
);
PREPARE fireball_two_factor_stmt FROM @migration_sql;
EXECUTE fireball_two_factor_stmt;
DEALLOCATE PREPARE fireball_two_factor_stmt;

SET @has_two_factor_recovery_codes = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'two_factor_recovery_codes'
);
SET @migration_sql = IF(
    @has_two_factor_recovery_codes = 0,
    'ALTER TABLE users ADD COLUMN two_factor_recovery_codes TEXT NULL AFTER two_factor_secret',
    'SELECT 1'
);
PREPARE fireball_two_factor_stmt FROM @migration_sql;
EXECUTE fireball_two_factor_stmt;
DEALLOCATE PREPARE fireball_two_factor_stmt;

SET @has_two_factor_enabled_at = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'two_factor_enabled_at'
);
SET @migration_sql = IF(
    @has_two_factor_enabled_at = 0,
    'ALTER TABLE users ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_recovery_codes',
    'SELECT 1'
);
PREPARE fireball_two_factor_stmt FROM @migration_sql;
EXECUTE fireball_two_factor_stmt;
DEALLOCATE PREPARE fireball_two_factor_stmt;
