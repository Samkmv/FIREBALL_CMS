SET @toy_rental_billing_type_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'toy_rental_rides'
          AND COLUMN_NAME = 'billing_type'
    ),
    'SELECT 1',
    'ALTER TABLE toy_rental_rides ADD COLUMN billing_type ENUM(''fixed'', ''metered'') NOT NULL DEFAULT ''fixed'' AFTER car_id'
);
PREPARE toy_rental_billing_type_stmt FROM @toy_rental_billing_type_sql;
EXECUTE toy_rental_billing_type_stmt;
DEALLOCATE PREPARE toy_rental_billing_type_stmt;

SET @toy_rental_minute_price_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'toy_rental_rides'
          AND COLUMN_NAME = 'price_per_minute'
    ),
    'SELECT 1',
    'ALTER TABLE toy_rental_rides ADD COLUMN price_per_minute DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER billing_type'
);
PREPARE toy_rental_minute_price_stmt FROM @toy_rental_minute_price_sql;
EXECUTE toy_rental_minute_price_stmt;
DEALLOCATE PREPARE toy_rental_minute_price_stmt;

SET @toy_rental_estimated_minutes_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'toy_rental_rides'
          AND COLUMN_NAME = 'estimated_minutes'
    ),
    'SELECT 1',
    'ALTER TABLE toy_rental_rides ADD COLUMN estimated_minutes INT(10) UNSIGNED NULL AFTER price_per_minute'
);
PREPARE toy_rental_estimated_minutes_stmt FROM @toy_rental_estimated_minutes_sql;
EXECUTE toy_rental_estimated_minutes_stmt;
DEALLOCATE PREPARE toy_rental_estimated_minutes_stmt;

SET @toy_rental_final_amount_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'toy_rental_rides'
          AND COLUMN_NAME = 'final_amount'
    ),
    'SELECT 1',
    'ALTER TABLE toy_rental_rides ADD COLUMN final_amount DECIMAL(10,2) NULL AFTER payment_amount'
);
PREPARE toy_rental_final_amount_stmt FROM @toy_rental_final_amount_sql;
EXECUTE toy_rental_final_amount_stmt;
DEALLOCATE PREPARE toy_rental_final_amount_stmt;

UPDATE toy_rental_rides
SET billing_type = 'fixed',
    final_amount = payment_amount
WHERE billing_type = 'fixed'
  AND final_amount IS NULL;
