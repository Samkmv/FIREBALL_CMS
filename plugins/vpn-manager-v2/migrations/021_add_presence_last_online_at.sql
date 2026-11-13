SET @vpn_presence_column_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscription_nodes'
          AND COLUMN_NAME = 'presence_last_online_at'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscription_nodes ADD COLUMN presence_last_online_at DATETIME NULL'
);
PREPARE vpn_presence_column_stmt FROM @vpn_presence_column_sql;
EXECUTE vpn_presence_column_stmt;
DEALLOCATE PREPARE vpn_presence_column_stmt;
