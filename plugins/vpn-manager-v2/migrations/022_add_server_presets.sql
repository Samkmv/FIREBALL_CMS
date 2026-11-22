CREATE TABLE IF NOT EXISTS vpn_v2_server_presets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vpn_v2_server_preset_nodes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    preset_id INT UNSIGNED NOT NULL,
    server_id INT UNSIGNED NOT NULL,
    inbound_id INT UNSIGNED NOT NULL,
    flow_override VARCHAR(80) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_preset_target (preset_id, server_id, inbound_id),
    KEY idx_v2_preset_server (server_id),
    KEY idx_v2_preset_inbound (inbound_id),
    CONSTRAINT fk_v2_preset_node_preset FOREIGN KEY (preset_id) REFERENCES vpn_v2_server_presets(id) ON DELETE CASCADE,
    CONSTRAINT fk_v2_preset_node_server FOREIGN KEY (server_id) REFERENCES vpn_v2_servers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_v2_preset_node_inbound FOREIGN KEY (inbound_id) REFERENCES vpn_v2_inbounds(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @vpn_v2_add_server_preset_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'vpn_v2_plans' AND COLUMN_NAME = 'server_preset_id'),
    'SELECT 1',
    'ALTER TABLE vpn_v2_plans ADD COLUMN server_preset_id INT UNSIGNED NULL DEFAULT NULL,
         ADD KEY idx_v2_plan_server_preset (server_preset_id),
         ADD CONSTRAINT fk_v2_plan_server_preset FOREIGN KEY (server_preset_id)
             REFERENCES vpn_v2_server_presets(id) ON DELETE RESTRICT'
);
PREPARE vpn_v2_add_server_preset_stmt FROM @vpn_v2_add_server_preset_sql;
EXECUTE vpn_v2_add_server_preset_stmt;
DEALLOCATE PREPARE vpn_v2_add_server_preset_stmt;
