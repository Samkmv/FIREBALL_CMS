ALTER TABLE vpn_v2_servers
    ADD COLUMN auth_type VARCHAR(20) NOT NULL DEFAULT 'token' AFTER panel_path;

CREATE INDEX idx_vpn_v2_servers_auth_type ON vpn_v2_servers (auth_type);
