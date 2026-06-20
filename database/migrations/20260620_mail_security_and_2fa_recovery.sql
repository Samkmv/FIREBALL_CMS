CREATE TABLE IF NOT EXISTS mail_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY status (status),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT(10) UNSIGNED NULL,
    target_user_id INT(10) UNSIGNED NULL,
    event VARCHAR(80) NOT NULL,
    result VARCHAR(30) NOT NULL DEFAULT 'success',
    reason TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY actor_user_id (actor_user_id),
    KEY target_user_id (target_user_id),
    KEY event (event),
    KEY result (result),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS two_factor_recovery_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY token_hash (token_hash),
    KEY user_id (user_id),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_password_reset_ip = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'password_resets'
      AND COLUMN_NAME = 'ip_address'
);
SET @password_reset_ip_sql = IF(
    @has_password_reset_ip = 0,
    'ALTER TABLE password_resets ADD COLUMN ip_address VARCHAR(45) NULL AFTER used_at',
    'SELECT 1'
);
PREPARE fireball_password_reset_ip_stmt FROM @password_reset_ip_sql;
EXECUTE fireball_password_reset_ip_stmt;
DEALLOCATE PREPARE fireball_password_reset_ip_stmt;

SET @has_password_reset_ua = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'password_resets'
      AND COLUMN_NAME = 'user_agent'
);
SET @password_reset_ua_sql = IF(
    @has_password_reset_ua = 0,
    'ALTER TABLE password_resets ADD COLUMN user_agent VARCHAR(500) NULL AFTER ip_address',
    'SELECT 1'
);
PREPARE fireball_password_reset_ua_stmt FROM @password_reset_ua_sql;
EXECUTE fireball_password_reset_ua_stmt;
DEALLOCATE PREPARE fireball_password_reset_ua_stmt;

SET @has_session_version = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'session_version'
);
SET @session_version_sql = IF(
    @has_session_version = 0,
    'ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER last_seen_at',
    'SELECT 1'
);
PREPARE fireball_session_version_stmt FROM @session_version_sql;
EXECUTE fireball_session_version_stmt;
DEALLOCATE PREPARE fireball_session_version_stmt;

SET @has_two_factor_notice = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'two_factor_reset_notice'
);
SET @two_factor_notice_sql = IF(
    @has_two_factor_notice = 0,
    'ALTER TABLE users ADD COLUMN two_factor_reset_notice TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER session_version',
    'SELECT 1'
);
PREPARE fireball_2fa_notice_stmt FROM @two_factor_notice_sql;
EXECUTE fireball_2fa_notice_stmt;
DEALLOCATE PREPARE fireball_2fa_notice_stmt;
