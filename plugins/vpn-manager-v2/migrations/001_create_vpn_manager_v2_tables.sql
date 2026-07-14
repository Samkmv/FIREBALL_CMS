CREATE TABLE IF NOT EXISTS vpn_v2_servers (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(80) NOT NULL,
    panel_url VARCHAR(500) NOT NULL,
    panel_path VARCHAR(190) NULL,
    encrypted_username MEDIUMTEXT NULL,
    encrypted_password MEDIUMTEXT NULL,
    encrypted_token MEDIUMTEXT NULL,
    country_code CHAR(2) NULL,
    country_name VARCHAR(120) NULL,
    city VARCHAR(120) NULL,
    show_flag TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'unchecked',
    is_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    last_check_at DATETIME NULL,
    last_success_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_servers_code (code),
    KEY idx_vpn_v2_servers_country_code (country_code),
    KEY idx_vpn_v2_servers_status (status),
    KEY idx_vpn_v2_servers_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_inbounds (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    server_id INT(10) UNSIGNED NOT NULL,
    remote_inbound_id VARCHAR(80) NOT NULL,
    name VARCHAR(255) NOT NULL,
    remark VARCHAR(255) NULL,
    protocol VARCHAR(40) NOT NULL,
    port INT(10) UNSIGNED NOT NULL,
    network VARCHAR(40) NULL,
    security VARCHAR(40) NULL,
    default_flow VARCHAR(80) NULL,
    settings_json MEDIUMTEXT NOT NULL,
    stream_settings_json MEDIUMTEXT NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    is_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    synced_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_inbounds_server_remote (server_id, remote_inbound_id),
    KEY idx_vpn_v2_inbounds_remote (remote_inbound_id),
    KEY idx_vpn_v2_inbounds_protocol (protocol),
    KEY idx_vpn_v2_inbounds_status (status),
    KEY idx_vpn_v2_inbounds_enabled (is_enabled),
    CONSTRAINT fk_v2_inbounds_server
        FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_plans (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    duration_days INT(10) UNSIGNED NOT NULL DEFAULT 30,
    traffic_limit_bytes BIGINT UNSIGNED NULL,
    device_limit INT(10) UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_vpn_v2_plans_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_plan_nodes (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_id INT(10) UNSIGNED NOT NULL,
    server_id INT(10) UNSIGNED NOT NULL,
    inbound_id INT(10) UNSIGNED NOT NULL,
    flow_override VARCHAR(80) NULL,
    is_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_plan_nodes_target (plan_id, server_id, inbound_id),
    KEY idx_vpn_v2_plan_nodes_server (server_id),
    KEY idx_vpn_v2_plan_nodes_inbound (inbound_id),
    KEY idx_vpn_v2_plan_nodes_enabled_order (is_enabled, sort_order),
    CONSTRAINT fk_v2_plan_nodes_plan
        FOREIGN KEY (plan_id) REFERENCES vpn_v2_plans(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_plan_nodes_server
        FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_v2_plan_nodes_inbound
        FOREIGN KEY (inbound_id) REFERENCES vpn_v2_inbounds(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    plan_id INT(10) UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'provisioning',
    starts_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    traffic_limit_bytes BIGINT UNSIGNED NULL,
    device_limit INT(10) UNSIGNED NOT NULL DEFAULT 1,
    subscription_token VARCHAR(128) NOT NULL,
    revision INT(10) UNSIGNED NOT NULL DEFAULT 1,
    config_updated_at DATETIME NULL,
    created_by INT(10) UNSIGNED NOT NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_subscriptions_token (subscription_token),
    KEY idx_vpn_v2_subscriptions_user (user_id),
    KEY idx_vpn_v2_subscriptions_plan (plan_id),
    KEY idx_vpn_v2_subscriptions_status (status),
    KEY idx_vpn_v2_subscriptions_expires (expires_at),
    KEY idx_vpn_v2_subscriptions_creator (created_by),
    KEY idx_vpn_v2_subscriptions_config_updated (config_updated_at),
    CONSTRAINT fk_v2_subscriptions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_v2_subscriptions_plan
        FOREIGN KEY (plan_id) REFERENCES vpn_v2_plans(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_v2_subscriptions_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_subscription_nodes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_id BIGINT UNSIGNED NOT NULL,
    server_id INT(10) UNSIGNED NOT NULL,
    inbound_id INT(10) UNSIGNED NOT NULL,
    remote_client_id VARCHAR(120) NULL,
    client_uuid VARCHAR(64) NOT NULL,
    client_email VARCHAR(190) NOT NULL,
    client_sub_id VARCHAR(120) NULL,
    protocol VARCHAR(40) NOT NULL,
    network VARCHAR(40) NULL,
    security VARCHAR(40) NULL,
    flow VARCHAR(80) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    traffic_limit_bytes BIGINT UNSIGNED NULL,
    traffic_used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_sync_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vpn_v2_sub_nodes_target (subscription_id, server_id, inbound_id),
    KEY idx_vpn_v2_sub_nodes_server (server_id),
    KEY idx_vpn_v2_sub_nodes_inbound (inbound_id),
    KEY idx_vpn_v2_sub_nodes_uuid (client_uuid),
    KEY idx_vpn_v2_sub_nodes_email (client_email),
    KEY idx_vpn_v2_sub_nodes_status (status),
    KEY idx_vpn_v2_sub_nodes_last_sync (last_sync_at),
    CONSTRAINT fk_v2_sub_nodes_subscription
        FOREIGN KEY (subscription_id) REFERENCES vpn_v2_subscriptions(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_v2_sub_nodes_server
        FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_v2_sub_nodes_inbound
        FOREIGN KEY (inbound_id) REFERENCES vpn_v2_inbounds(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(120) NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    node_id BIGINT UNSIGNED NULL,
    server_id INT(10) UNSIGNED NULL,
    user_id INT(10) UNSIGNED NULL,
    admin_id INT(10) UNSIGNED NULL,
    context_json MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_vpn_v2_events_type_created (event_type, created_at),
    KEY idx_vpn_v2_events_subscription (subscription_id),
    KEY idx_vpn_v2_events_node (node_id),
    KEY idx_vpn_v2_events_server (server_id),
    KEY idx_vpn_v2_events_user (user_id),
    KEY idx_vpn_v2_events_admin (admin_id),
    KEY idx_vpn_v2_events_created (created_at),
    CONSTRAINT fk_v2_events_subscription
        FOREIGN KEY (subscription_id) REFERENCES vpn_v2_subscriptions(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_events_node
        FOREIGN KEY (node_id) REFERENCES vpn_v2_subscription_nodes(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_events_server
        FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_events_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_v2_events_admin
        FOREIGN KEY (admin_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
