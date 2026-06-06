SET @has_legal_information = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pages'
      AND COLUMN_NAME = 'show_in_legal_information'
);

SET @column_sql = IF(
    @has_legal_information = 0,
    'ALTER TABLE pages ADD COLUMN show_in_legal_information TINYINT(1) NOT NULL DEFAULT 0 AFTER show_in_footer',
    'SELECT 1'
);

PREPARE fireball_migration_stmt FROM @column_sql;
EXECUTE fireball_migration_stmt;
DEALLOCATE PREPARE fireball_migration_stmt;

SET @has_legal_information_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pages'
      AND INDEX_NAME = 'show_in_legal_information'
);

SET @index_sql = IF(
    @has_legal_information_index = 0,
    'ALTER TABLE pages ADD KEY show_in_legal_information (show_in_legal_information, is_published, menu_order, title)',
    'SELECT 1'
);

PREPARE fireball_index_stmt FROM @index_sql;
EXECUTE fireball_index_stmt;
DEALLOCATE PREPARE fireball_index_stmt;
