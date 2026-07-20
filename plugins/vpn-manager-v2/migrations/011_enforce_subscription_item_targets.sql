SET @vpn_v2_subscription_item_guard_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_items'
             AND COLUMN_NAME = 'target_guard'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_items ADD COLUMN target_guard TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER relation_key'
);
PREPARE vpn_v2_subscription_item_guard_stmt FROM @vpn_v2_subscription_item_guard_sql;
EXECUTE vpn_v2_subscription_item_guard_stmt;
DEALLOCATE PREPARE vpn_v2_subscription_item_guard_stmt;

DROP TRIGGER IF EXISTS vpn_v2_subscription_items_validate_insert;
CREATE TRIGGER vpn_v2_subscription_items_validate_insert
BEFORE INSERT ON vpn_v2_subscription_items
FOR EACH ROW
SET NEW.target_guard = IF(
    (
        (NEW.item_type = 'subscription' AND NEW.child_subscription_id IS NOT NULL AND NEW.connection_id IS NULL)
        OR
        (NEW.item_type = 'connection' AND NEW.connection_id IS NOT NULL AND NEW.child_subscription_id IS NULL)
    )
    AND NEW.ownership_type IN ('exclusive', 'shared'),
    1,
    NULL
);

DROP TRIGGER IF EXISTS vpn_v2_subscription_items_validate_update;
CREATE TRIGGER vpn_v2_subscription_items_validate_update
BEFORE UPDATE ON vpn_v2_subscription_items
FOR EACH ROW
SET NEW.target_guard = IF(
    (
        (NEW.item_type = 'subscription' AND NEW.child_subscription_id IS NOT NULL AND NEW.connection_id IS NULL)
        OR
        (NEW.item_type = 'connection' AND NEW.connection_id IS NOT NULL AND NEW.child_subscription_id IS NULL)
    )
    AND NEW.ownership_type IN ('exclusive', 'shared'),
    1,
    NULL
);

