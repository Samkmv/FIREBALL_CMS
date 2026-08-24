CREATE TABLE IF NOT EXISTS vpn_v2_access_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL,
    handled_at DATETIME NULL,
    handled_by INT(10) UNSIGNED NULL,
    subscription_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_vpn_v2_access_requests_user (user_id, requested_at),
    KEY idx_vpn_v2_access_requests_status (status, requested_at),
    KEY idx_vpn_v2_access_requests_handler (handled_by),
    KEY idx_vpn_v2_access_requests_subscription (subscription_id),
    CONSTRAINT fk_v2_access_requests_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_access_requests_handler
        FOREIGN KEY (handled_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_access_requests_subscription
        FOREIGN KEY (subscription_id) REFERENCES vpn_v2_subscriptions(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
