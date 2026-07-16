ALTER TABLE vpn_v2_subscription_nodes
    ADD COLUMN desired_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER status,
    ADD COLUMN is_obsolete TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER desired_enabled,
    ADD COLUMN expires_at DATETIME NULL AFTER is_obsolete,
    ADD COLUMN device_limit INT(10) UNSIGNED NOT NULL DEFAULT 1 AFTER expires_at,
    ADD COLUMN upload_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER traffic_used_bytes,
    ADD COLUMN download_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER upload_bytes,
    ADD COLUMN traffic_synced_at DATETIME NULL AFTER download_bytes,
    ADD COLUMN traffic_sync_status VARCHAR(40) NOT NULL DEFAULT 'pending' AFTER traffic_synced_at,
    ADD KEY idx_vpn_v2_sub_nodes_obsolete (is_obsolete),
    ADD KEY idx_vpn_v2_sub_nodes_traffic_status (traffic_sync_status);

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
