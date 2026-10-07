-- Keep existing subscription sources intact; a source belongs to one owner scope.
ALTER TABLE vpn_v2_external_sources MODIFY parent_subscription_id BIGINT UNSIGNED NULL DEFAULT NULL;

SET @vpn_v2_external_plan_column_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'vpn_v2_external_sources' AND COLUMN_NAME = 'plan_id'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_external_sources ADD COLUMN plan_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER parent_subscription_id'
);
PREPARE vpn_v2_external_plan_column_stmt FROM @vpn_v2_external_plan_column_sql;
EXECUTE vpn_v2_external_plan_column_stmt;
DEALLOCATE PREPARE vpn_v2_external_plan_column_stmt;

SET @vpn_v2_external_plan_unique_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'vpn_v2_external_sources' AND INDEX_NAME = 'uq_vpn_v2_external_sources_plan'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_external_sources ADD UNIQUE KEY uq_vpn_v2_external_sources_plan (plan_id, relation_key)'
);
PREPARE vpn_v2_external_plan_unique_stmt FROM @vpn_v2_external_plan_unique_sql;
EXECUTE vpn_v2_external_plan_unique_stmt;
DEALLOCATE PREPARE vpn_v2_external_plan_unique_stmt;

SET @vpn_v2_external_plan_order_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'vpn_v2_external_sources' AND INDEX_NAME = 'idx_vpn_v2_external_sources_plan_order'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_external_sources ADD KEY idx_vpn_v2_external_sources_plan_order (plan_id, sort_order, id)'
);
PREPARE vpn_v2_external_plan_order_stmt FROM @vpn_v2_external_plan_order_sql;
EXECUTE vpn_v2_external_plan_order_stmt;
DEALLOCATE PREPARE vpn_v2_external_plan_order_stmt;
