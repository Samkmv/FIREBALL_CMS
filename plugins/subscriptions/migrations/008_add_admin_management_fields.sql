SET @subscriptions_source_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'source'
);
SET @subscriptions_source_column_sql = IF(
    @subscriptions_source_column_exists = 0,
    'ALTER TABLE `subscriptions` ADD COLUMN `source` VARCHAR(30) NOT NULL DEFAULT ''robokassa'' AFTER `admin_comment`',
    'SELECT 1'
);
PREPARE subscriptions_source_column_statement FROM @subscriptions_source_column_sql;
EXECUTE subscriptions_source_column_statement;
DEALLOCATE PREPARE subscriptions_source_column_statement;

UPDATE subscriptions SET source = 'manual'
WHERE admin_comment IS NOT NULL AND admin_comment != '' AND source = 'robokassa';

UPDATE subscriptions AS s
LEFT JOIN subscription_payments AS p ON p.subscription_id = s.id
SET s.source = 'manual'
WHERE s.source = 'robokassa' AND p.id IS NULL;

SET @subscriptions_cleared_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_payments' AND COLUMN_NAME = 'cleared_at'
);
SET @subscriptions_cleared_column_sql = IF(
    @subscriptions_cleared_column_exists = 0,
    'ALTER TABLE `subscription_payments` ADD COLUMN `cleared_at` DATETIME NULL AFTER `failed_at`, ADD KEY `idx_subscription_payments_visible` (`cleared_at`, `created_at`)',
    'SELECT 1'
);
PREPARE subscriptions_cleared_column_statement FROM @subscriptions_cleared_column_sql;
EXECUTE subscriptions_cleared_column_statement;
DEALLOCATE PREPARE subscriptions_cleared_column_statement;
