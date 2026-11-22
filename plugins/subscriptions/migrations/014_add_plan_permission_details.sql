SET @subscriptions_permission_details_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'permission_details_json'
);
SET @subscriptions_permission_details_sql = IF(
    @subscriptions_permission_details_exists = 0,
    'ALTER TABLE `subscription_plans` ADD COLUMN `permission_details_json` LONGTEXT NULL AFTER `features_json`',
    'SELECT 1'
);
PREPARE subscriptions_permission_details_statement FROM @subscriptions_permission_details_sql;
EXECUTE subscriptions_permission_details_statement;
DEALLOCATE PREPARE subscriptions_permission_details_statement;
