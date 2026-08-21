SET @subscriptions_popular_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscription_plans'
      AND COLUMN_NAME = 'is_popular'
);

SET @subscriptions_popular_column_sql = IF(
    @subscriptions_popular_column_exists = 0,
    'ALTER TABLE `subscription_plans` ADD COLUMN `is_popular` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `is_public`',
    'SELECT 1'
);

PREPARE subscriptions_popular_column_statement FROM @subscriptions_popular_column_sql;
EXECUTE subscriptions_popular_column_statement;
DEALLOCATE PREPARE subscriptions_popular_column_statement;
