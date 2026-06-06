SET @has_token_hash = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'password_resets'
      AND COLUMN_NAME = 'token_hash'
);

SET @has_legacy_token = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'password_resets'
      AND COLUMN_NAME = 'token'
);

SET @migration_sql = IF(
    @has_token_hash = 0 AND @has_legacy_token = 1,
    'ALTER TABLE password_resets CHANGE COLUMN token token_hash VARCHAR(64) NOT NULL',
    IF(
        @has_token_hash = 0,
        'ALTER TABLE password_resets ADD COLUMN token_hash VARCHAR(64) NOT NULL AFTER email',
        'SELECT 1'
    )
);

PREPARE fireball_migration_stmt FROM @migration_sql;
EXECUTE fireball_migration_stmt;
DEALLOCATE PREPARE fireball_migration_stmt;

SET @has_token_hash_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'password_resets'
      AND INDEX_NAME = 'token_hash'
);

SET @index_sql = IF(
    @has_token_hash_index = 0,
    'ALTER TABLE password_resets ADD UNIQUE KEY token_hash (token_hash)',
    'SELECT 1'
);

PREPARE fireball_index_stmt FROM @index_sql;
EXECUTE fireball_index_stmt;
DEALLOCATE PREPARE fireball_index_stmt;
