CREATE TABLE IF NOT EXISTS reel_playback_state (
    owner_id INT(10) UNSIGNED NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
    device VARCHAR(64) NOT NULL DEFAULT '',
    state_json MEDIUMTEXT NULL,
    playing TINYINT(1) NOT NULL DEFAULT 0,
    updated_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (owner_id),
    CONSTRAINT fk_reel_playback_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
