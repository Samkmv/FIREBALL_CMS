CREATE TABLE IF NOT EXISTS camera_manager_sites (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(190) NOT NULL,
    address VARCHAR(255) NULL,
    router_ip VARCHAR(45) NULL,
    recorder_ip VARCHAR(45) NOT NULL,
    vpn_ip VARCHAR(45) NULL,
    wireguard_public_key VARCHAR(190) NULL,
    rtsp_port SMALLINT(5) UNSIGNED NOT NULL DEFAULT 554,
    management_port SMALLINT(5) UNSIGNED NOT NULL DEFAULT 37777,
    rtsp_username VARCHAR(190) NOT NULL,
    rtsp_password_encrypted TEXT NOT NULL,
    rtsp_path_template VARCHAR(500) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY camera_manager_sites_code (code),
    KEY camera_manager_sites_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS camera_manager_cameras (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT(10) UNSIGNED NOT NULL,
    stream_key VARCHAR(64) NOT NULL,
    name VARCHAR(190) NOT NULL,
    channel_number SMALLINT(5) UNSIGNED NOT NULL,
    subtype SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    rtsp_path_override VARCHAR(500) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_health_status ENUM('unknown', 'online', 'offline') NOT NULL DEFAULT 'unknown',
    last_health_message VARCHAR(500) NULL,
    last_checked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY camera_manager_cameras_stream_key (stream_key),
    UNIQUE KEY camera_manager_cameras_site_channel (site_id, channel_number),
    KEY camera_manager_cameras_enabled (enabled),
    CONSTRAINT camera_manager_cameras_site_fk FOREIGN KEY (site_id)
        REFERENCES camera_manager_sites (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS camera_manager_publications (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NULL,
    stream_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('success', 'warning', 'failed') NOT NULL,
    backup_path VARCHAR(500) NULL,
    message VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY camera_manager_publications_created (created_at),
    KEY camera_manager_publications_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
