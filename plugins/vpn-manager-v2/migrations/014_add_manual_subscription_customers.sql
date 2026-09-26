-- FIREBALL_VPN_MANUAL_CUSTOMERS_V1
-- VPN Manager V2 1.3.0
-- Allow VPN subscriptions/profiles without a CMS account.

SET @vpn_v2_subscription_user_nullable_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscriptions'
          AND COLUMN_NAME = 'user_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE vpn_v2_subscriptions
        MODIFY COLUMN user_id INT(10) UNSIGNED NULL',
    'SELECT 1'
);
PREPARE vpn_v2_subscription_user_nullable_stmt
    FROM @vpn_v2_subscription_user_nullable_sql;
EXECUTE vpn_v2_subscription_user_nullable_stmt;
DEALLOCATE PREPARE vpn_v2_subscription_user_nullable_stmt;

SET @vpn_v2_manual_customer_name_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscriptions'
          AND COLUMN_NAME = 'manual_customer_name'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions
        ADD COLUMN manual_customer_name VARCHAR(190) NULL AFTER user_id'
);
PREPARE vpn_v2_manual_customer_name_stmt
    FROM @vpn_v2_manual_customer_name_sql;
EXECUTE vpn_v2_manual_customer_name_stmt;
DEALLOCATE PREPARE vpn_v2_manual_customer_name_stmt;

SET @vpn_v2_manual_customer_index_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscriptions'
          AND INDEX_NAME = 'idx_vpn_v2_subscriptions_manual_customer'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions
        ADD KEY idx_vpn_v2_subscriptions_manual_customer (manual_customer_name)'
);
PREPARE vpn_v2_manual_customer_index_stmt
    FROM @vpn_v2_manual_customer_index_sql;
EXECUTE vpn_v2_manual_customer_index_stmt;
DEALLOCATE PREPARE vpn_v2_manual_customer_index_stmt;

SET @vpn_v2_profile_user_nullable_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_profiles'
          AND COLUMN_NAME = 'cms_user_id'
          AND IS_NULLABLE = 'NO'
    ),
    'ALTER TABLE vpn_v2_profiles
        MODIFY COLUMN cms_user_id INT(10) UNSIGNED NULL',
    'SELECT 1'
);
PREPARE vpn_v2_profile_user_nullable_stmt
    FROM @vpn_v2_profile_user_nullable_sql;
EXECUTE vpn_v2_profile_user_nullable_stmt;
DEALLOCATE PREPARE vpn_v2_profile_user_nullable_stmt;
