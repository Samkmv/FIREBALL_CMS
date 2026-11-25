SET @subscriptions_homepage_category_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'homepage_category'
);
SET @subscriptions_homepage_category_sql = IF(
    @subscriptions_homepage_category_exists = 0,
    'ALTER TABLE `subscription_plans` ADD COLUMN `homepage_category` VARCHAR(32) NOT NULL DEFAULT '''' AFTER `description`',
    'SELECT 1'
);
PREPARE subscriptions_homepage_category_statement FROM @subscriptions_homepage_category_sql;
EXECUTE subscriptions_homepage_category_statement;
DEALLOCATE PREPARE subscriptions_homepage_category_statement;
