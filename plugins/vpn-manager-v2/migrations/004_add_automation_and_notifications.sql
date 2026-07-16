SET @vpn_v2_traffic_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vpn_v2_subscriptions'
          AND COLUMN_NAME = 'traffic_used_bytes'
    ),
    'SELECT 1',
    'ALTER TABLE vpn_v2_subscriptions ADD COLUMN traffic_used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER traffic_limit_bytes'
);
PREPARE vpn_v2_traffic_stmt FROM @vpn_v2_traffic_sql;
EXECUTE vpn_v2_traffic_stmt;
DEALLOCATE PREPARE vpn_v2_traffic_stmt;

UPDATE vpn_v2_subscriptions s
SET s.traffic_used_bytes = (
    SELECT COALESCE(SUM(n.traffic_used_bytes), 0)
    FROM vpn_v2_subscription_nodes n
    WHERE n.subscription_id = s.id AND n.status <> 'deleted'
);

CREATE TABLE IF NOT EXISTS vpn_v2_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_id BIGINT UNSIGNED NOT NULL,
    user_id INT(10) UNSIGNED NOT NULL,
    notification_type VARCHAR(80) NOT NULL,
    occurrence_key VARCHAR(120) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts INT(10) UNSIGNED NOT NULL DEFAULT 0,
    scheduled_for DATETIME NOT NULL,
    sent_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_notifications_dedupe
        (subscription_id, notification_type, occurrence_key, channel),
    KEY idx_vpn_v2_notifications_pending (status, scheduled_for, id),
    KEY idx_vpn_v2_notifications_user (user_id, created_at),
    KEY idx_vpn_v2_notifications_subscription (subscription_id, created_at),
    CONSTRAINT fk_v2_notifications_subscription
        FOREIGN KEY (subscription_id) REFERENCES vpn_v2_subscriptions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
