SET @vpn_v2_client_display_name_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'vpn_v2_subscriptions' AND COLUMN_NAME = 'client_display_name'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD COLUMN client_display_name VARCHAR(160) NULL DEFAULT NULL AFTER manual_customer_name'
);
PREPARE vpn_v2_client_display_name_stmt FROM @vpn_v2_client_display_name_sql;
EXECUTE vpn_v2_client_display_name_stmt;
DEALLOCATE PREPARE vpn_v2_client_display_name_stmt;
