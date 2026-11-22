SET @subscriptions_features_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'features_json'
);
SET @subscriptions_features_sql = IF(
    @subscriptions_features_exists = 0,
    'ALTER TABLE `subscription_plans` ADD COLUMN `features_json` LONGTEXT NULL AFTER `description`',
    'SELECT 1'
);
PREPARE subscriptions_features_statement FROM @subscriptions_features_sql;
EXECUTE subscriptions_features_statement;
DEALLOCATE PREPARE subscriptions_features_statement;
