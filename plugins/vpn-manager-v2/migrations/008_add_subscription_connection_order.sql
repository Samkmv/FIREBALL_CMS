SET @vpn_v2_node_sort_order_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscription_nodes'
          AND COLUMN_NAME = 'sort_order'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN sort_order BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER inbound_id'
);
PREPARE vpn_v2_node_sort_order_stmt FROM @vpn_v2_node_sort_order_sql;
EXECUTE vpn_v2_node_sort_order_stmt;
DEALLOCATE PREPARE vpn_v2_node_sort_order_stmt;

UPDATE vpn_v2_subscription_nodes
SET sort_order = id
WHERE sort_order = 0;

SET @vpn_v2_node_sort_index_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscription_nodes'
          AND INDEX_NAME = 'idx_vpn_v2_sub_nodes_order'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD KEY idx_vpn_v2_sub_nodes_order (subscription_id, sort_order, id)'
);
PREPARE vpn_v2_node_sort_index_stmt FROM @vpn_v2_node_sort_index_sql;
EXECUTE vpn_v2_node_sort_index_stmt;
DEALLOCATE PREPARE vpn_v2_node_sort_index_stmt;
