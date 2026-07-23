SET @vpn_v2_desired_enabled_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'desired_enabled'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN desired_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER status'
);
PREPARE vpn_v2_desired_enabled_stmt FROM @vpn_v2_desired_enabled_sql;
EXECUTE vpn_v2_desired_enabled_stmt;
DEALLOCATE PREPARE vpn_v2_desired_enabled_stmt;

SET @vpn_v2_is_obsolete_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'is_obsolete'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN is_obsolete TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER desired_enabled'
);
PREPARE vpn_v2_is_obsolete_stmt FROM @vpn_v2_is_obsolete_sql;
EXECUTE vpn_v2_is_obsolete_stmt;
DEALLOCATE PREPARE vpn_v2_is_obsolete_stmt;

SET @vpn_v2_reconcile_expires_at_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'expires_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN expires_at DATETIME NULL AFTER is_obsolete'
);
PREPARE vpn_v2_reconcile_expires_at_stmt FROM @vpn_v2_reconcile_expires_at_sql;
EXECUTE vpn_v2_reconcile_expires_at_stmt;
DEALLOCATE PREPARE vpn_v2_reconcile_expires_at_stmt;

SET @vpn_v2_reconcile_device_limit_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'device_limit'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN device_limit INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER expires_at'
);
PREPARE vpn_v2_reconcile_device_limit_stmt FROM @vpn_v2_reconcile_device_limit_sql;
EXECUTE vpn_v2_reconcile_device_limit_stmt;
DEALLOCATE PREPARE vpn_v2_reconcile_device_limit_stmt;

SET @vpn_v2_upload_bytes_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'upload_bytes'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN upload_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER traffic_used_bytes'
);
PREPARE vpn_v2_upload_bytes_stmt FROM @vpn_v2_upload_bytes_sql;
EXECUTE vpn_v2_upload_bytes_stmt;
DEALLOCATE PREPARE vpn_v2_upload_bytes_stmt;

SET @vpn_v2_download_bytes_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'download_bytes'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN download_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER upload_bytes'
);
PREPARE vpn_v2_download_bytes_stmt FROM @vpn_v2_download_bytes_sql;
EXECUTE vpn_v2_download_bytes_stmt;
DEALLOCATE PREPARE vpn_v2_download_bytes_stmt;

SET @vpn_v2_traffic_synced_at_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'traffic_synced_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN traffic_synced_at DATETIME NULL AFTER download_bytes'
);
PREPARE vpn_v2_traffic_synced_at_stmt FROM @vpn_v2_traffic_synced_at_sql;
EXECUTE vpn_v2_traffic_synced_at_stmt;
DEALLOCATE PREPARE vpn_v2_traffic_synced_at_stmt;

SET @vpn_v2_traffic_sync_status_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND COLUMN_NAME = 'traffic_sync_status'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN traffic_sync_status VARCHAR(40) NOT NULL DEFAULT ''pending'' AFTER traffic_synced_at'
);
PREPARE vpn_v2_traffic_sync_status_stmt FROM @vpn_v2_traffic_sync_status_sql;
EXECUTE vpn_v2_traffic_sync_status_stmt;
DEALLOCATE PREPARE vpn_v2_traffic_sync_status_stmt;

SET @vpn_v2_obsolete_index_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND INDEX_NAME = 'idx_vpn_v2_sub_nodes_obsolete'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD KEY idx_vpn_v2_sub_nodes_obsolete (is_obsolete)'
);
PREPARE vpn_v2_obsolete_index_stmt FROM @vpn_v2_obsolete_index_sql;
EXECUTE vpn_v2_obsolete_index_stmt;
DEALLOCATE PREPARE vpn_v2_obsolete_index_stmt;

SET @vpn_v2_traffic_status_index_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
             AND INDEX_NAME = 'idx_vpn_v2_sub_nodes_traffic_status'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD KEY idx_vpn_v2_sub_nodes_traffic_status (traffic_sync_status)'
);
PREPARE vpn_v2_traffic_status_index_stmt FROM @vpn_v2_traffic_status_index_sql;
EXECUTE vpn_v2_traffic_status_index_stmt;
DEALLOCATE PREPARE vpn_v2_traffic_status_index_stmt;

UPDATE vpn_v2_subscription_nodes n
INNER JOIN vpn_v2_subscriptions s ON s.id = n.subscription_id
SET n.desired_enabled = CASE
    WHEN s.status IN ('suspended', 'expired', 'traffic_exceeded', 'deleting', 'delete_failed', 'deleted') THEN 0
    ELSE 1
END,
    n.expires_at = s.expires_at,
    n.device_limit = s.device_limit;

CREATE TABLE IF NOT EXISTS vpn_v2_reconcile_operations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_id CHAR(36) NOT NULL,
    plan_id INT(10) UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    initiated_by INT(10) UNSIGNED NULL,
    last_subscription_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    batch_size INT(10) UNSIGNED NOT NULL DEFAULT 20,
    options_json MEDIUMTEXT NULL,
    total_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    processed_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    success_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    failure_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    skipped_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_reconcile_operation_id (operation_id),
    KEY idx_vpn_v2_reconcile_plan_status (plan_id, status),
    KEY idx_vpn_v2_reconcile_status_updated (status, updated_at),
    CONSTRAINT fk_v2_reconcile_plan
        FOREIGN KEY (plan_id) REFERENCES vpn_v2_plans(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_v2_reconcile_initiator
        FOREIGN KEY (initiated_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
