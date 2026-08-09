SET @subscriptions_billing_period_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscription_payments'
      AND COLUMN_NAME = 'billing_period_start'
);

SET @subscriptions_billing_period_column_sql = IF(
    @subscriptions_billing_period_column_exists = 0,
    'ALTER TABLE `subscription_payments` ADD COLUMN `billing_period_start` DATETIME NULL AFTER `subscription_id`',
    'SELECT 1'
);
PREPARE subscriptions_billing_period_column_statement FROM @subscriptions_billing_period_column_sql;
EXECUTE subscriptions_billing_period_column_statement;
DEALLOCATE PREPARE subscriptions_billing_period_column_statement;

SET @subscriptions_recurring_period_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscription_payments'
      AND INDEX_NAME = 'uq_subscription_recurring_period'
);

SET @subscriptions_recurring_period_index_sql = IF(
    @subscriptions_recurring_period_index_exists = 0,
    'ALTER TABLE `subscription_payments` ADD UNIQUE KEY `uq_subscription_recurring_period` (`subscription_id`, `payment_type`, `billing_period_start`)',
    'SELECT 1'
);
PREPARE subscriptions_recurring_period_index_statement FROM @subscriptions_recurring_period_index_sql;
EXECUTE subscriptions_recurring_period_index_statement;
DEALLOCATE PREPARE subscriptions_recurring_period_index_statement;

UPDATE subscription_profile_fields
SET is_required = 0, updated_at = NOW()
WHERE field_key IN ('first_name', 'last_name');

UPDATE subscription_profile_fields
SET is_required = 1, is_active = 1, updated_at = NOW()
WHERE field_key IN ('email', 'phone', 'country', 'region', 'city', 'street', 'house', 'postal_code');
