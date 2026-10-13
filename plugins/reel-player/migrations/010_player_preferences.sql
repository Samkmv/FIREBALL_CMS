CREATE TABLE IF NOT EXISTS reel_player_preferences (
    owner_id INT(10) UNSIGNED NOT NULL,
    display_mode VARCHAR(12) NOT NULL DEFAULT 'reel',
    sitewide_player TINYINT(1) NOT NULL DEFAULT 0,
    remember_queue TINYINT(1) NOT NULL DEFAULT 1,
    restore_position TINYINT(1) NOT NULL DEFAULT 1,
    theme VARCHAR(12) NOT NULL DEFAULT 'light',
    personalization TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (owner_id),
    CONSTRAINT fk_reel_preferences_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
