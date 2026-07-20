SET @vpn_v2_repair_subscription_hash_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscriptions'
             AND COLUMN_NAME = 'subscription_token_hash'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD COLUMN subscription_token_hash CHAR(64) NULL AFTER subscription_token'
);
PREPARE vpn_v2_repair_subscription_hash_stmt FROM @vpn_v2_repair_subscription_hash_sql;
EXECUTE vpn_v2_repair_subscription_hash_stmt;
DEALLOCATE PREPARE vpn_v2_repair_subscription_hash_stmt;

UPDATE vpn_v2_subscriptions
SET subscription_token_hash = SHA2(subscription_token, 256)
WHERE subscription_token_hash IS NULL OR subscription_token_hash = '';

SET @vpn_v2_repair_subscription_hash_index_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscriptions'
             AND INDEX_NAME = 'idx_vpn_v2_subscriptions_token_hash'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD KEY idx_vpn_v2_subscriptions_token_hash (subscription_token_hash)'
);
PREPARE vpn_v2_repair_subscription_hash_index_stmt FROM @vpn_v2_repair_subscription_hash_index_sql;
EXECUTE vpn_v2_repair_subscription_hash_index_stmt;
DEALLOCATE PREPARE vpn_v2_repair_subscription_hash_index_stmt;

SET @vpn_v2_repair_node_credential_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'encrypted_client_credential'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN encrypted_client_credential MEDIUMTEXT NULL AFTER client_uuid'
);
PREPARE vpn_v2_repair_node_credential_stmt FROM @vpn_v2_repair_node_credential_sql;
EXECUTE vpn_v2_repair_node_credential_stmt;
DEALLOCATE PREPARE vpn_v2_repair_node_credential_stmt;

SET @vpn_v2_repair_remote_credential_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_remote_clients'
             AND COLUMN_NAME = 'encrypted_client_credential'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_remote_clients ADD COLUMN encrypted_client_credential MEDIUMTEXT NULL AFTER client_uuid'
);
PREPARE vpn_v2_repair_remote_credential_stmt FROM @vpn_v2_repair_remote_credential_sql;
EXECUTE vpn_v2_repair_remote_credential_stmt;
DEALLOCATE PREPARE vpn_v2_repair_remote_credential_stmt;

