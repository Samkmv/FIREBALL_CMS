SET @toy_rental_deleted_at_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'toy_rental_cars'
          AND COLUMN_NAME = 'deleted_at'
    ),
    'SELECT 1',
    'ALTER TABLE toy_rental_cars ADD COLUMN deleted_at DATETIME NULL AFTER updated_at'
);
PREPARE toy_rental_deleted_at_stmt FROM @toy_rental_deleted_at_sql;
EXECUTE toy_rental_deleted_at_stmt;
DEALLOCATE PREPARE toy_rental_deleted_at_stmt;
