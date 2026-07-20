SET @vpn_v2_plan_deleted_at_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_plans'
             AND COLUMN_NAME = 'deleted_at'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_plans ADD COLUMN deleted_at DATETIME NULL AFTER updated_at, ADD KEY idx_vpn_v2_plans_deleted (deleted_at)'
);
PREPARE vpn_v2_plan_deleted_at_stmt FROM @vpn_v2_plan_deleted_at_sql;
EXECUTE vpn_v2_plan_deleted_at_stmt;
DEALLOCATE PREPARE vpn_v2_plan_deleted_at_stmt;

CREATE TABLE IF NOT EXISTS vpn_v2_external_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_subscription_id BIGINT UNSIGNED NOT NULL,
    source_type VARCHAR(32) NOT NULL,
    name VARCHAR(160) NOT NULL,
    encrypted_source LONGTEXT NOT NULL,
    source_hash CHAR(64) NOT NULL,
    source_preview VARCHAR(255) NOT NULL,
    encrypted_snapshot LONGTEXT NULL,
    snapshot_hash CHAR(64) NULL,
    config_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
    sync_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    last_sync_at DATETIME NULL,
    last_error TEXT NULL,
    relation_key CHAR(64) NULL,
    created_by INT(10) UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_external_sources_active (parent_subscription_id, relation_key),
    KEY idx_vpn_v2_external_sources_parent (parent_subscription_id),
    KEY idx_vpn_v2_external_sources_type (source_type),
    KEY idx_vpn_v2_external_sources_enabled (is_enabled),
    KEY idx_vpn_v2_external_sources_order (parent_subscription_id, sort_order, id),
    KEY idx_vpn_v2_external_sources_sync (sync_status, last_sync_at),
    KEY idx_vpn_v2_external_sources_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
