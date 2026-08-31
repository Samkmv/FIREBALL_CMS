SET @camera_manager_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camera_manager_sites' AND COLUMN_NAME = 'lan_cidr'
);
SET @camera_manager_sql = IF(
    @camera_manager_column_exists = 0,
    'ALTER TABLE camera_manager_sites ADD COLUMN lan_cidr VARCHAR(49) NULL AFTER vpn_ip',
    'SELECT 1'
);
PREPARE camera_manager_stmt FROM @camera_manager_sql;
EXECUTE camera_manager_stmt;
DEALLOCATE PREPARE camera_manager_stmt;

SET @camera_manager_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camera_manager_sites' AND COLUMN_NAME = 'external_rtsp_port'
);
SET @camera_manager_sql = IF(
    @camera_manager_column_exists = 0,
    'ALTER TABLE camera_manager_sites ADD COLUMN external_rtsp_port SMALLINT(5) UNSIGNED NULL AFTER rtsp_port',
    'SELECT 1'
);
PREPARE camera_manager_stmt FROM @camera_manager_sql;
EXECUTE camera_manager_stmt;
DEALLOCATE PREPARE camera_manager_stmt;

ALTER TABLE camera_manager_sites
    MODIFY COLUMN management_port SMALLINT(5) UNSIGNED NULL DEFAULT NULL;

SET @camera_manager_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camera_manager_sites' AND COLUMN_NAME = 'external_management_port'
);
SET @camera_manager_sql = IF(
    @camera_manager_column_exists = 0,
    'ALTER TABLE camera_manager_sites ADD COLUMN external_management_port SMALLINT(5) UNSIGNED NULL AFTER management_port',
    'SELECT 1'
);
PREPARE camera_manager_stmt FROM @camera_manager_sql;
EXECUTE camera_manager_stmt;
DEALLOCATE PREPARE camera_manager_stmt;

SET @camera_manager_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camera_manager_sites' AND COLUMN_NAME = 'rtsp_profile'
);
SET @camera_manager_sql = IF(
    @camera_manager_column_exists = 0,
    'ALTER TABLE camera_manager_sites ADD COLUMN rtsp_profile VARCHAR(40) NULL AFTER rtsp_path_template',
    'SELECT 1'
);
PREPARE camera_manager_stmt FROM @camera_manager_sql;
EXECUTE camera_manager_stmt;
DEALLOCATE PREPARE camera_manager_stmt;

SET @camera_manager_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camera_manager_sites' AND COLUMN_NAME = 'rtsp_stream_mode'
);
SET @camera_manager_sql = IF(
    @camera_manager_column_exists = 0,
    'ALTER TABLE camera_manager_sites ADD COLUMN rtsp_stream_mode VARCHAR(20) NULL AFTER rtsp_profile',
    'SELECT 1'
);
PREPARE camera_manager_stmt FROM @camera_manager_sql;
EXECUTE camera_manager_stmt;
DEALLOCATE PREPARE camera_manager_stmt;

SET @camera_manager_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camera_manager_sites' AND COLUMN_NAME = 'network_setup_status'
);
SET @camera_manager_sql = IF(
    @camera_manager_column_exists = 0,
    'ALTER TABLE camera_manager_sites ADD COLUMN network_setup_status VARCHAR(30) NOT NULL DEFAULT ''not_configured'' AFTER rtsp_stream_mode',
    'SELECT 1'
);
PREPARE camera_manager_stmt FROM @camera_manager_sql;
EXECUTE camera_manager_stmt;
DEALLOCATE PREPARE camera_manager_stmt;

SET @camera_manager_column_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'camera_manager_sites' AND COLUMN_NAME = 'network_notes'
);
SET @camera_manager_sql = IF(
    @camera_manager_column_exists = 0,
    'ALTER TABLE camera_manager_sites ADD COLUMN network_notes TEXT NULL AFTER network_setup_status',
    'SELECT 1'
);
PREPARE camera_manager_stmt FROM @camera_manager_sql;
EXECUTE camera_manager_stmt;
DEALLOCATE PREPARE camera_manager_stmt;

CREATE TABLE IF NOT EXISTS camera_manager_diagnostic_jobs (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id CHAR(32) NOT NULL,
    sequence_number SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
    site_id INT(10) UNSIGNED NOT NULL,
    operation VARCHAR(40) NOT NULL,
    parameters_json MEDIUMTEXT NULL,
    status ENUM('pending', 'running', 'success', 'warning', 'failed') NOT NULL DEFAULT 'pending',
    result_json MEDIUMTEXT NULL,
    error_code VARCHAR(64) NULL,
    message VARCHAR(500) NULL,
    created_by INT(10) UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    dispatched_at DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY camera_manager_diagnostic_site (site_id, id),
    KEY camera_manager_diagnostic_queue (status, id),
    KEY camera_manager_diagnostic_batch (batch_id, sequence_number),
    CONSTRAINT camera_manager_diagnostic_site_fk FOREIGN KEY (site_id)
        REFERENCES camera_manager_sites (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
