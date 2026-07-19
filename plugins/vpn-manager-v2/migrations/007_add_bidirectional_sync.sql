SET @vpn_v2_server_api_url_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'api_url'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN api_url VARCHAR(500) NULL AFTER panel_path'
);
PREPARE vpn_v2_server_api_url_stmt FROM @vpn_v2_server_api_url_sql;
EXECUTE vpn_v2_server_api_url_stmt;
DEALLOCATE PREPARE vpn_v2_server_api_url_stmt;

SET @vpn_v2_server_maintenance_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'maintenance_mode'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN maintenance_mode TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER is_enabled'
);
PREPARE vpn_v2_server_maintenance_stmt FROM @vpn_v2_server_maintenance_sql;
EXECUTE vpn_v2_server_maintenance_stmt;
DEALLOCATE PREPARE vpn_v2_server_maintenance_stmt;

SET @vpn_v2_server_allow_new_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'allow_new_connections'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN allow_new_connections TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER maintenance_mode'
);
PREPARE vpn_v2_server_allow_new_stmt FROM @vpn_v2_server_allow_new_sql;
EXECUTE vpn_v2_server_allow_new_stmt;
DEALLOCATE PREPARE vpn_v2_server_allow_new_stmt;

SET @vpn_v2_server_verify_ssl_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'verify_ssl'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN verify_ssl TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER allow_new_connections'
);
PREPARE vpn_v2_server_verify_ssl_stmt FROM @vpn_v2_server_verify_ssl_sql;
EXECUTE vpn_v2_server_verify_ssl_stmt;
DEALLOCATE PREPARE vpn_v2_server_verify_ssl_stmt;

SET @vpn_v2_server_private_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'allow_private_network'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN allow_private_network TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER verify_ssl'
);
PREPARE vpn_v2_server_private_stmt FROM @vpn_v2_server_private_sql;
EXECUTE vpn_v2_server_private_stmt;
DEALLOCATE PREPARE vpn_v2_server_private_stmt;

SET @vpn_v2_server_connect_timeout_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'connect_timeout'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN connect_timeout INT(10) UNSIGNED NOT NULL DEFAULT 5 AFTER allow_private_network'
);
PREPARE vpn_v2_server_connect_timeout_stmt FROM @vpn_v2_server_connect_timeout_sql;
EXECUTE vpn_v2_server_connect_timeout_stmt;
DEALLOCATE PREPARE vpn_v2_server_connect_timeout_stmt;

SET @vpn_v2_server_read_timeout_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'read_timeout'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN read_timeout INT(10) UNSIGNED NOT NULL DEFAULT 15 AFTER connect_timeout'
);
PREPARE vpn_v2_server_read_timeout_stmt FROM @vpn_v2_server_read_timeout_sql;
EXECUTE vpn_v2_server_read_timeout_stmt;
DEALLOCATE PREPARE vpn_v2_server_read_timeout_stmt;

SET @vpn_v2_server_last_auth_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'last_auth_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN last_auth_at DATETIME NULL AFTER last_success_at'
);
PREPARE vpn_v2_server_last_auth_stmt FROM @vpn_v2_server_last_auth_sql;
EXECUTE vpn_v2_server_last_auth_stmt;
DEALLOCATE PREPARE vpn_v2_server_last_auth_stmt;

SET @vpn_v2_server_last_sync_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'last_sync_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN last_sync_at DATETIME NULL AFTER last_auth_at'
);
PREPARE vpn_v2_server_last_sync_stmt FROM @vpn_v2_server_last_sync_sql;
EXECUTE vpn_v2_server_last_sync_stmt;
DEALLOCATE PREPARE vpn_v2_server_last_sync_stmt;

SET @vpn_v2_server_api_version_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'api_version'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN api_version VARCHAR(80) NULL AFTER last_sync_at'
);
PREPARE vpn_v2_server_api_version_stmt FROM @vpn_v2_server_api_version_sql;
EXECUTE vpn_v2_server_api_version_stmt;
DEALLOCATE PREPARE vpn_v2_server_api_version_stmt;

SET @vpn_v2_server_capabilities_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_servers' AND COLUMN_NAME = 'capabilities_json'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN capabilities_json MEDIUMTEXT NULL AFTER api_version'
);
PREPARE vpn_v2_server_capabilities_stmt FROM @vpn_v2_server_capabilities_sql;
EXECUTE vpn_v2_server_capabilities_stmt;
DEALLOCATE PREPARE vpn_v2_server_capabilities_stmt;

CREATE TABLE IF NOT EXISTS vpn_v2_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cms_user_id INT(10) UNSIGNED NOT NULL,
    shared_uuid VARCHAR(64) NOT NULL,
    encrypted_shared_password MEDIUMTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_profiles_user (cms_user_id),
    UNIQUE KEY uq_vpn_v2_profiles_uuid (shared_uuid),
    KEY idx_vpn_v2_profiles_status (status),
    CONSTRAINT fk_v2_profiles_user FOREIGN KEY (cms_user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @vpn_v2_subscription_profile_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscriptions' AND COLUMN_NAME = 'profile_id'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD COLUMN profile_id BIGINT UNSIGNED NULL AFTER user_id, ADD KEY idx_vpn_v2_subscriptions_profile (profile_id), ADD CONSTRAINT fk_v2_subscriptions_profile FOREIGN KEY (profile_id) REFERENCES vpn_v2_profiles(id) ON UPDATE CASCADE ON DELETE RESTRICT'
);
PREPARE vpn_v2_subscription_profile_stmt FROM @vpn_v2_subscription_profile_sql;
EXECUTE vpn_v2_subscription_profile_stmt;
DEALLOCATE PREPARE vpn_v2_subscription_profile_stmt;

SET @vpn_v2_subscription_hash_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscriptions' AND COLUMN_NAME = 'config_hash'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD COLUMN config_hash CHAR(64) NULL AFTER revision'
);
PREPARE vpn_v2_subscription_hash_stmt FROM @vpn_v2_subscription_hash_sql;
EXECUTE vpn_v2_subscription_hash_stmt;
DEALLOCATE PREPARE vpn_v2_subscription_hash_stmt;

SET @vpn_v2_subscription_token_hash_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscriptions' AND COLUMN_NAME = 'subscription_token_hash'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD COLUMN subscription_token_hash CHAR(64) NULL AFTER subscription_token, ADD KEY idx_vpn_v2_subscriptions_token_hash (subscription_token_hash)'
);
PREPARE vpn_v2_subscription_token_hash_stmt FROM @vpn_v2_subscription_token_hash_sql;
EXECUTE vpn_v2_subscription_token_hash_stmt;
DEALLOCATE PREPARE vpn_v2_subscription_token_hash_stmt;

UPDATE vpn_v2_subscriptions
SET subscription_token_hash = SHA2(subscription_token, 256)
WHERE subscription_token_hash IS NULL OR subscription_token_hash = '';

SET @vpn_v2_node_remote_name_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'remote_client_name'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN remote_client_name VARCHAR(190) NULL AFTER client_email, ADD KEY idx_vpn_v2_sub_nodes_remote_name (server_id, inbound_id, remote_client_name)'
);
PREPARE vpn_v2_node_remote_name_stmt FROM @vpn_v2_node_remote_name_sql;
EXECUTE vpn_v2_node_remote_name_stmt;
DEALLOCATE PREPARE vpn_v2_node_remote_name_stmt;

SET @vpn_v2_node_encrypted_credential_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'encrypted_client_credential'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN encrypted_client_credential MEDIUMTEXT NULL AFTER client_uuid'
);
PREPARE vpn_v2_node_encrypted_credential_stmt FROM @vpn_v2_node_encrypted_credential_sql;
EXECUTE vpn_v2_node_encrypted_credential_stmt;
DEALLOCATE PREPARE vpn_v2_node_encrypted_credential_stmt;

SET @vpn_v2_node_country_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'country_code'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN country_code CHAR(2) NULL AFTER remote_client_name'
);
PREPARE vpn_v2_node_country_stmt FROM @vpn_v2_node_country_sql;
EXECUTE vpn_v2_node_country_stmt;
DEALLOCATE PREPARE vpn_v2_node_country_stmt;

SET @vpn_v2_node_sync_status_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'sync_status'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN sync_status VARCHAR(40) NOT NULL DEFAULT \'pending\' AFTER traffic_sync_status, ADD KEY idx_vpn_v2_sub_nodes_sync_status (sync_status)'
);
PREPARE vpn_v2_node_sync_status_stmt FROM @vpn_v2_node_sync_status_sql;
EXECUTE vpn_v2_node_sync_status_stmt;
DEALLOCATE PREPARE vpn_v2_node_sync_status_stmt;

SET @vpn_v2_node_sync_error_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'sync_error'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN sync_error TEXT NULL AFTER sync_status'
);
PREPARE vpn_v2_node_sync_error_stmt FROM @vpn_v2_node_sync_error_sql;
EXECUTE vpn_v2_node_sync_error_stmt;
DEALLOCATE PREPARE vpn_v2_node_sync_error_stmt;

SET @vpn_v2_node_remote_hash_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'last_remote_hash'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN last_remote_hash CHAR(64) NULL AFTER sync_error'
);
PREPARE vpn_v2_node_remote_hash_stmt FROM @vpn_v2_node_remote_hash_sql;
EXECUTE vpn_v2_node_remote_hash_stmt;
DEALLOCATE PREPARE vpn_v2_node_remote_hash_stmt;

SET @vpn_v2_node_local_hash_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'last_local_hash'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN last_local_hash CHAR(64) NULL AFTER last_remote_hash'
);
PREPARE vpn_v2_node_local_hash_stmt FROM @vpn_v2_node_local_hash_sql;
EXECUTE vpn_v2_node_local_hash_stmt;
DEALLOCATE PREPARE vpn_v2_node_local_hash_stmt;

SET @vpn_v2_node_last_seen_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'last_seen_remote_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN last_seen_remote_at DATETIME NULL AFTER last_local_hash'
);
PREPARE vpn_v2_node_last_seen_stmt FROM @vpn_v2_node_last_seen_sql;
EXECUTE vpn_v2_node_last_seen_stmt;
DEALLOCATE PREPARE vpn_v2_node_last_seen_stmt;

SET @vpn_v2_node_operation_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'last_operation_id'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN last_operation_id CHAR(36) NULL AFTER last_seen_remote_at'
);
PREPARE vpn_v2_node_operation_stmt FROM @vpn_v2_node_operation_sql;
EXECUTE vpn_v2_node_operation_stmt;
DEALLOCATE PREPARE vpn_v2_node_operation_stmt;

SET @vpn_v2_node_snapshot_json_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'lkg_snapshot_json'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN lkg_snapshot_json MEDIUMTEXT NULL AFTER last_operation_id'
);
PREPARE vpn_v2_node_snapshot_json_stmt FROM @vpn_v2_node_snapshot_json_sql;
EXECUTE vpn_v2_node_snapshot_json_stmt;
DEALLOCATE PREPARE vpn_v2_node_snapshot_json_stmt;

SET @vpn_v2_node_snapshot_hash_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'lkg_snapshot_hash'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN lkg_snapshot_hash CHAR(64) NULL AFTER lkg_snapshot_json'
);
PREPARE vpn_v2_node_snapshot_hash_stmt FROM @vpn_v2_node_snapshot_hash_sql;
EXECUTE vpn_v2_node_snapshot_hash_stmt;
DEALLOCATE PREPARE vpn_v2_node_snapshot_hash_stmt;

SET @vpn_v2_node_snapshot_version_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'lkg_snapshot_version'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN lkg_snapshot_version INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER lkg_snapshot_hash'
);
PREPARE vpn_v2_node_snapshot_version_stmt FROM @vpn_v2_node_snapshot_version_sql;
EXECUTE vpn_v2_node_snapshot_version_stmt;
DEALLOCATE PREPARE vpn_v2_node_snapshot_version_stmt;

SET @vpn_v2_node_snapshot_received_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'lkg_received_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN lkg_received_at DATETIME NULL AFTER lkg_snapshot_version'
);
PREPARE vpn_v2_node_snapshot_received_stmt FROM @vpn_v2_node_snapshot_received_sql;
EXECUTE vpn_v2_node_snapshot_received_stmt;
DEALLOCATE PREPARE vpn_v2_node_snapshot_received_stmt;

SET @vpn_v2_node_snapshot_confirmed_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'lkg_confirmed_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN lkg_confirmed_at DATETIME NULL AFTER lkg_received_at'
);
PREPARE vpn_v2_node_snapshot_confirmed_stmt FROM @vpn_v2_node_snapshot_confirmed_sql;
EXECUTE vpn_v2_node_snapshot_confirmed_stmt;
DEALLOCATE PREPARE vpn_v2_node_snapshot_confirmed_stmt;

SET @vpn_v2_node_snapshot_source_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'lkg_source'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN lkg_source VARCHAR(40) NULL AFTER lkg_confirmed_at'
);
PREPARE vpn_v2_node_snapshot_source_stmt FROM @vpn_v2_node_snapshot_source_sql;
EXECUTE vpn_v2_node_snapshot_source_stmt;
DEALLOCATE PREPARE vpn_v2_node_snapshot_source_stmt;

SET @vpn_v2_node_snapshot_validity_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes' AND COLUMN_NAME = 'lkg_validity'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN lkg_validity VARCHAR(40) NOT NULL DEFAULT \'unknown\' AFTER lkg_source'
);
PREPARE vpn_v2_node_snapshot_validity_stmt FROM @vpn_v2_node_snapshot_validity_sql;
EXECUTE vpn_v2_node_snapshot_validity_stmt;
DEALLOCATE PREPARE vpn_v2_node_snapshot_validity_stmt;

CREATE TABLE IF NOT EXISTS vpn_v2_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_id CHAR(36) NOT NULL,
    idempotency_key CHAR(64) NULL,
    operation_type VARCHAR(60) NOT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'cms',
    server_id INT(10) UNSIGNED NULL,
    subscription_id BIGINT UNSIGNED NULL,
    connection_id BIGINT UNSIGNED NULL,
    payload_json MEDIUMTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    attempts INT(10) UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT(10) UNSIGNED NOT NULL DEFAULT 8,
    next_attempt_at DATETIME NOT NULL,
    lease_until DATETIME NULL,
    heartbeat_at DATETIME NULL,
    last_error TEXT NULL,
    initiated_by INT(10) UNSIGNED NULL,
    processed_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    total_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_operations_id (operation_id),
    UNIQUE KEY uq_vpn_v2_operations_idempotency (idempotency_key),
    KEY idx_vpn_v2_operations_due (status, next_attempt_at, id),
    KEY idx_vpn_v2_operations_server (server_id, status),
    KEY idx_vpn_v2_operations_subscription (subscription_id, status),
    KEY idx_vpn_v2_operations_connection (connection_id, status),
    CONSTRAINT fk_v2_operations_server FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_operations_subscription FOREIGN KEY (subscription_id) REFERENCES vpn_v2_subscriptions(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_operations_connection FOREIGN KEY (connection_id) REFERENCES vpn_v2_subscription_nodes(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_operations_initiator FOREIGN KEY (initiated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_connection_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    connection_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    server_id INT(10) UNSIGNED NOT NULL,
    inbound_id INT(10) UNSIGNED NOT NULL,
    snapshot_version INT(10) UNSIGNED NOT NULL,
    config_hash CHAR(64) NOT NULL,
    snapshot_json MEDIUMTEXT NOT NULL,
    source VARCHAR(40) NOT NULL,
    validity VARCHAR(40) NOT NULL DEFAULT 'valid',
    operation_id CHAR(36) NULL,
    received_at DATETIME NOT NULL,
    confirmed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_snapshots_version (connection_id, snapshot_version),
    KEY idx_vpn_v2_snapshots_subscription (subscription_id, created_at),
    KEY idx_vpn_v2_snapshots_server (server_id, created_at),
    KEY idx_vpn_v2_snapshots_hash (config_hash),
    KEY idx_vpn_v2_snapshots_validity (validity),
    CONSTRAINT fk_v2_snapshots_connection FOREIGN KEY (connection_id) REFERENCES vpn_v2_subscription_nodes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_snapshots_subscription FOREIGN KEY (subscription_id) REFERENCES vpn_v2_subscriptions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_snapshots_server FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_v2_snapshots_inbound FOREIGN KEY (inbound_id) REFERENCES vpn_v2_inbounds(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_sync_conflicts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conflict_type VARCHAR(60) NOT NULL,
    server_id INT(10) UNSIGNED NULL,
    subscription_id BIGINT UNSIGNED NULL,
    connection_id BIGINT UNSIGNED NULL,
    local_value TEXT NULL,
    remote_value TEXT NULL,
    recommended_action VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    operation_id CHAR(36) NULL,
    detected_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    resolved_by INT(10) UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_vpn_v2_conflicts_status (status, detected_at),
    KEY idx_vpn_v2_conflicts_server (server_id, status),
    KEY idx_vpn_v2_conflicts_subscription (subscription_id, status),
    KEY idx_vpn_v2_conflicts_connection (connection_id, status),
    CONSTRAINT fk_v2_conflicts_server FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_conflicts_subscription FOREIGN KEY (subscription_id) REFERENCES vpn_v2_subscriptions(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_conflicts_connection FOREIGN KEY (connection_id) REFERENCES vpn_v2_subscription_nodes(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_conflicts_resolver FOREIGN KEY (resolved_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_sync_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_id CHAR(36) NULL,
    operation_type VARCHAR(60) NOT NULL,
    source VARCHAR(40) NOT NULL,
    server_id INT(10) UNSIGNED NULL,
    subscription_id BIGINT UNSIGNED NULL,
    user_id INT(10) UNSIGNED NULL,
    connection_id BIGINT UNSIGNED NULL,
    previous_hash CHAR(64) NULL,
    new_hash CHAR(64) NULL,
    changed_fields_json TEXT NULL,
    status VARCHAR(40) NOT NULL,
    error_code VARCHAR(120) NULL,
    safe_error TEXT NULL,
    duration_ms INT(10) UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_vpn_v2_sync_logs_created (created_at),
    KEY idx_vpn_v2_sync_logs_operation (operation_id),
    KEY idx_vpn_v2_sync_logs_server (server_id, created_at),
    KEY idx_vpn_v2_sync_logs_subscription (subscription_id, created_at),
    KEY idx_vpn_v2_sync_logs_connection (connection_id, created_at),
    KEY idx_vpn_v2_sync_logs_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_remote_clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    server_id INT(10) UNSIGNED NOT NULL,
    inbound_id INT(10) UNSIGNED NOT NULL,
    remote_client_id VARCHAR(120) NULL,
    remote_client_name VARCHAR(190) NULL,
    client_uuid VARCHAR(64) NULL,
    encrypted_client_credential MEDIUMTEXT NULL,
    remote_hash CHAR(64) NOT NULL,
    normalized_json MEDIUMTEXT NOT NULL,
    management_status VARCHAR(40) NOT NULL DEFAULT 'unmanaged_remote',
    connection_id BIGINT UNSIGNED NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_remote_client_hash (server_id, inbound_id, remote_hash),
    KEY idx_vpn_v2_remote_clients_remote_id (server_id, remote_client_id),
    KEY idx_vpn_v2_remote_clients_uuid (client_uuid),
    KEY idx_vpn_v2_remote_clients_name (server_id, inbound_id, remote_client_name),
    KEY idx_vpn_v2_remote_clients_status (management_status, last_seen_at),
    CONSTRAINT fk_v2_remote_clients_server FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_remote_clients_inbound FOREIGN KEY (inbound_id) REFERENCES vpn_v2_inbounds(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_remote_clients_connection FOREIGN KEY (connection_id) REFERENCES vpn_v2_subscription_nodes(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @vpn_v2_remote_client_credential_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_remote_clients' AND COLUMN_NAME = 'encrypted_client_credential'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_remote_clients ADD COLUMN encrypted_client_credential MEDIUMTEXT NULL AFTER client_uuid'
);
PREPARE vpn_v2_remote_client_credential_stmt FROM @vpn_v2_remote_client_credential_sql;
EXECUTE vpn_v2_remote_client_credential_stmt;
DEALLOCATE PREPARE vpn_v2_remote_client_credential_stmt;

UPDATE vpn_v2_subscription_nodes n
INNER JOIN vpn_v2_servers s ON s.id = n.server_id
SET n.country_code = COALESCE(n.country_code, s.country_code),
    n.remote_client_name = COALESCE(NULLIF(n.remote_client_name, ''), n.client_email),
    n.sync_status = CASE
        WHEN n.status IN ('active', 'disabled') THEN 'synced'
        WHEN n.status IN ('creating', 'create_failed') THEN 'pending'
        ELSE n.status
    END;
