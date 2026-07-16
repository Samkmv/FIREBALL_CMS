SET @vpn_v2_expires_at_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscription_nodes'
          AND COLUMN_NAME = 'expires_at'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN expires_at DATETIME NULL AFTER is_obsolete'
);
PREPARE vpn_v2_expires_at_stmt FROM @vpn_v2_expires_at_sql;
EXECUTE vpn_v2_expires_at_stmt;
DEALLOCATE PREPARE vpn_v2_expires_at_stmt;

SET @vpn_v2_device_limit_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscription_nodes'
          AND COLUMN_NAME = 'device_limit'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN device_limit INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER expires_at'
);
PREPARE vpn_v2_device_limit_stmt FROM @vpn_v2_device_limit_sql;
EXECUTE vpn_v2_device_limit_stmt;
DEALLOCATE PREPARE vpn_v2_device_limit_stmt;

UPDATE vpn_v2_subscription_nodes n
INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
SET n.expires_at = s.expires_at,
    n.device_limit = s.device_limit;
