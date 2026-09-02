CREATE TABLE IF NOT EXISTS subscription_address_exclusions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    address VARCHAR(500) NOT NULL,
    normalized_address VARCHAR(500) NOT NULL,
    rule_type VARCHAR(30) NOT NULL DEFAULT 'street',
    street_type VARCHAR(30) NULL,
    normalized_street VARCHAR(255) NOT NULL,
    normalized_house VARCHAR(50) NULL,
    normalized_apartment VARCHAR(50) NULL,
    comment TEXT NULL,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_address_exclusion_normalized (normalized_address),
    KEY idx_subscription_address_exclusion_match (is_active, normalized_street, normalized_house),
    KEY idx_subscription_address_exclusion_created (created_at),
    CONSTRAINT fk_subscription_address_exclusion_created_by FOREIGN KEY (created_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscription_address_exclusion_updated_by FOREIGN KEY (updated_by)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @subscriptions_plan_auto_renew_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'auto_renew_enabled'
);
SET @subscriptions_plan_auto_renew_column_sql = IF(
    @subscriptions_plan_auto_renew_column_exists = 0,
    'ALTER TABLE `subscription_plans` ADD COLUMN `auto_renew_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `is_recurring`',
    'SELECT 1'
);
PREPARE subscriptions_plan_auto_renew_column_statement FROM @subscriptions_plan_auto_renew_column_sql;
EXECUTE subscriptions_plan_auto_renew_column_statement;
DEALLOCATE PREPARE subscriptions_plan_auto_renew_column_statement;

UPDATE subscription_plans
SET auto_renew_enabled = is_recurring
WHERE auto_renew_enabled = 0 AND is_recurring = 1;

SET @subscriptions_archived_at_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscriptions' AND COLUMN_NAME = 'archived_at'
);
SET @subscriptions_archived_at_column_sql = IF(
    @subscriptions_archived_at_column_exists = 0,
    'ALTER TABLE `subscriptions` ADD COLUMN `archived_at` DATETIME NULL AFTER `updated_at`, ADD KEY `idx_subscriptions_archived` (`archived_at`, `user_id`)',
    'SELECT 1'
);
PREPARE subscriptions_archived_at_column_statement FROM @subscriptions_archived_at_column_sql;
EXECUTE subscriptions_archived_at_column_statement;
DEALLOCATE PREPARE subscriptions_archived_at_column_statement;

SET @subscriptions_profile_address_excluded_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_profiles' AND COLUMN_NAME = 'address_excluded'
);
SET @subscriptions_profile_address_excluded_column_sql = IF(
    @subscriptions_profile_address_excluded_column_exists = 0,
    'ALTER TABLE `subscription_profiles` ADD COLUMN `address_excluded` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `data_completed_at`',
    'SELECT 1'
);
PREPARE subscriptions_profile_address_excluded_column_statement FROM @subscriptions_profile_address_excluded_column_sql;
EXECUTE subscriptions_profile_address_excluded_column_statement;
DEALLOCATE PREPARE subscriptions_profile_address_excluded_column_statement;

SET @subscriptions_profile_matched_exclusion_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_profiles' AND COLUMN_NAME = 'matched_address_exclusion_id'
);
SET @subscriptions_profile_matched_exclusion_column_sql = IF(
    @subscriptions_profile_matched_exclusion_column_exists = 0,
    'ALTER TABLE `subscription_profiles` ADD COLUMN `matched_address_exclusion_id` BIGINT UNSIGNED NULL AFTER `address_excluded`, ADD KEY `idx_subscription_profiles_address_exclusion` (`matched_address_exclusion_id`)',
    'SELECT 1'
);
PREPARE subscriptions_profile_matched_exclusion_column_statement FROM @subscriptions_profile_matched_exclusion_column_sql;
EXECUTE subscriptions_profile_matched_exclusion_column_statement;
DEALLOCATE PREPARE subscriptions_profile_matched_exclusion_column_statement;

SET @subscriptions_profile_address_checked_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_profiles' AND COLUMN_NAME = 'address_checked_at'
);
SET @subscriptions_profile_address_checked_column_sql = IF(
    @subscriptions_profile_address_checked_column_exists = 0,
    'ALTER TABLE `subscription_profiles` ADD COLUMN `address_checked_at` DATETIME NULL AFTER `matched_address_exclusion_id`',
    'SELECT 1'
);
PREPARE subscriptions_profile_address_checked_column_statement FROM @subscriptions_profile_address_checked_column_sql;
EXECUTE subscriptions_profile_address_checked_column_statement;
DEALLOCATE PREPARE subscriptions_profile_address_checked_column_statement;

SET @subscriptions_profile_address_exclusion_fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'subscription_profiles'
      AND CONSTRAINT_NAME = 'fk_subscription_profiles_address_exclusion'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @subscriptions_profile_address_exclusion_fk_sql = IF(
    @subscriptions_profile_address_exclusion_fk_exists = 0,
    'ALTER TABLE `subscription_profiles` ADD CONSTRAINT `fk_subscription_profiles_address_exclusion` FOREIGN KEY (`matched_address_exclusion_id`) REFERENCES `subscription_address_exclusions` (`id`) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE subscriptions_profile_address_exclusion_fk_statement FROM @subscriptions_profile_address_exclusion_fk_sql;
EXECUTE subscriptions_profile_address_exclusion_fk_statement;
DEALLOCATE PREPARE subscriptions_profile_address_exclusion_fk_statement;
