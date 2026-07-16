SET @vpn_v2_comment_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscriptions'
          AND COLUMN_NAME = 'internal_comment'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD COLUMN internal_comment TEXT NULL AFTER created_by'
);
PREPARE vpn_v2_comment_stmt FROM @vpn_v2_comment_sql;
EXECUTE vpn_v2_comment_stmt;
DEALLOCATE PREPARE vpn_v2_comment_stmt;
