CREATE TABLE IF NOT EXISTS reel_artist_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(240) NOT NULL,
    owner_id INT(10) UNSIGNED NOT NULL,
    image_path VARCHAR(100) NULL,
    image_mime VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_reel_artist_name (name_key),
    KEY idx_reel_artist_owner (owner_id),
    CONSTRAINT fk_reel_artist_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
