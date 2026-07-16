SET @vpn_v2_auth_type_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_servers'
          AND COLUMN_NAME = 'auth_type'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_servers ADD COLUMN auth_type VARCHAR(20) NOT NULL DEFAULT ''token'' AFTER panel_path'
);
PREPARE vpn_v2_auth_type_stmt FROM @vpn_v2_auth_type_sql;
EXECUTE vpn_v2_auth_type_stmt;
DEALLOCATE PREPARE vpn_v2_auth_type_stmt;

SET @vpn_v2_auth_index_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_servers'
          AND INDEX_NAME = 'idx_vpn_v2_servers_auth_type'
    ),
    'SELECT 1',
    'CREATE INDEX idx_vpn_v2_servers_auth_type ON vpn_v2_servers (auth_type)'
);
PREPARE vpn_v2_auth_index_stmt FROM @vpn_v2_auth_index_sql;
EXECUTE vpn_v2_auth_index_stmt;
DEALLOCATE PREPARE vpn_v2_auth_index_stmt;
