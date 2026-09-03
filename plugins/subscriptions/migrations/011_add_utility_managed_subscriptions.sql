SET @subscriptions_utility_managed_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'utility_managed'
);
SET @subscriptions_utility_managed_column_sql = IF(
    @subscriptions_utility_managed_column_exists = 0,
    'ALTER TABLE `subscriptions` ADD COLUMN `utility_managed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `source`, ADD KEY `idx_subscriptions_utility_managed` (`user_id`, `utility_managed`, `archived_at`)',
    'SELECT 1'
);
PREPARE subscriptions_utility_managed_column_statement FROM @subscriptions_utility_managed_column_sql;
EXECUTE subscriptions_utility_managed_column_statement;
DEALLOCATE PREPARE subscriptions_utility_managed_column_statement;

SET @subscriptions_address_exclusion_id_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'address_exclusion_id'
);
SET @subscriptions_address_exclusion_id_column_sql = IF(
    @subscriptions_address_exclusion_id_column_exists = 0,
    'ALTER TABLE `subscriptions` ADD COLUMN `address_exclusion_id` BIGINT UNSIGNED NULL AFTER `utility_managed`, ADD KEY `idx_subscriptions_address_exclusion` (`address_exclusion_id`)',
    'SELECT 1'
);
PREPARE subscriptions_address_exclusion_id_column_statement FROM @subscriptions_address_exclusion_id_column_sql;
EXECUTE subscriptions_address_exclusion_id_column_statement;
DEALLOCATE PREPARE subscriptions_address_exclusion_id_column_statement;

SET @subscriptions_address_exclusion_fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscriptions'
      AND CONSTRAINT_NAME = 'fk_subscriptions_address_exclusion'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @subscriptions_address_exclusion_fk_sql = IF(
    @subscriptions_address_exclusion_fk_exists = 0,
    'ALTER TABLE `subscriptions` ADD CONSTRAINT `fk_subscriptions_address_exclusion` FOREIGN KEY (`address_exclusion_id`) REFERENCES `subscription_address_exclusions` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE subscriptions_address_exclusion_fk_statement FROM @subscriptions_address_exclusion_fk_sql;
EXECUTE subscriptions_address_exclusion_fk_statement;
DEALLOCATE PREPARE subscriptions_address_exclusion_fk_statement;
