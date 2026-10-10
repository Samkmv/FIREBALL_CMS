CREATE TABLE IF NOT EXISTS vpn_v2_server_health (
    server_id INT UNSIGNED NOT NULL PRIMARY KEY,
    state VARCHAR(20) NOT NULL DEFAULT 'unknown',
    consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
    last_check_at DATETIME NULL,
    last_success_at DATETIME NULL,
    next_check_at DATETIME NOT NULL,
    snapshot_json MEDIUMTEXT NULL,
    KEY idx_vpn_v2_health_due (next_check_at),
    CONSTRAINT fk_vpn_v2_health_server FOREIGN KEY (server_id) REFERENCES vpn_v2_servers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_server_health_checks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    server_id INT UNSIGNED NOT NULL,
    state VARCHAR(20) NOT NULL,
    checked_at DATETIME NOT NULL,
    snapshot_json MEDIUMTEXT NOT NULL,
    KEY idx_vpn_v2_health_history (server_id, id),
    CONSTRAINT fk_vpn_v2_health_check_server FOREIGN KEY (server_id) REFERENCES vpn_v2_servers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
