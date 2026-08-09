SET @subscriptions_plan_snapshot_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscription_orders'
      AND COLUMN_NAME = 'plan_snapshot'
);

SET @subscriptions_plan_snapshot_column_sql = IF(
    @subscriptions_plan_snapshot_column_exists = 0,
    'ALTER TABLE `subscription_orders` ADD COLUMN `plan_snapshot` MEDIUMTEXT NULL AFTER `currency`',
    'SELECT 1'
);
PREPARE subscriptions_plan_snapshot_column_statement FROM @subscriptions_plan_snapshot_column_sql;
EXECUTE subscriptions_plan_snapshot_column_statement;
DEALLOCATE PREPARE subscriptions_plan_snapshot_column_statement;

UPDATE subscription_orders AS orders
INNER JOIN subscription_plans AS plans ON plans.id = orders.plan_id
SET orders.plan_snapshot = JSON_OBJECT(
    'id', plans.id,
    'slug', plans.slug,
    'name', plans.name,
    'description', plans.description,
    'price_minor', plans.price_minor,
    'currency', plans.currency,
    'duration_unit', plans.duration_unit,
    'duration_value', plans.duration_value,
    'grace_period_days', plans.grace_period_days,
    'is_recurring', plans.is_recurring
)
WHERE orders.plan_snapshot IS NULL OR orders.plan_snapshot = '';

ALTER TABLE subscription_orders MODIFY COLUMN plan_snapshot MEDIUMTEXT NOT NULL;
