CREATE TABLE IF NOT EXISTS vpn_v2_subscription_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_subscription_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(24) NOT NULL,
    child_subscription_id BIGINT UNSIGNED NULL,
    connection_id BIGINT UNSIGNED NULL,
    ownership_type VARCHAR(24) NOT NULL DEFAULT 'shared',
    is_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
    effective_status VARCHAR(40) NOT NULL DEFAULT 'inactive',
    inactive_reason VARCHAR(80) NULL,
    last_evaluated_at DATETIME NULL,
    relation_key VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_subscription_items_active (parent_subscription_id, relation_key),
    KEY idx_vpn_v2_subscription_items_parent (parent_subscription_id),
    KEY idx_vpn_v2_subscription_items_child (child_subscription_id),
    KEY idx_vpn_v2_subscription_items_connection (connection_id),
    KEY idx_vpn_v2_subscription_items_type (item_type),
    KEY idx_vpn_v2_subscription_items_enabled (is_enabled),
    KEY idx_vpn_v2_subscription_items_order (parent_subscription_id, sort_order, id),
    KEY idx_vpn_v2_subscription_items_deleted (deleted_at),
    CONSTRAINT chk_vpn_v2_subscription_items_target CHECK (
        (item_type = 'subscription' AND child_subscription_id IS NOT NULL AND connection_id IS NULL)
        OR
        (item_type = 'connection' AND connection_id IS NOT NULL AND child_subscription_id IS NULL)
    ),
    CONSTRAINT chk_vpn_v2_subscription_items_ownership CHECK (
        ownership_type IN ('exclusive', 'shared')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

