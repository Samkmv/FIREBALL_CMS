CREATE TABLE IF NOT EXISTS user_favorites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY user_entity (user_id, entity_type, entity_id),
    KEY user_created (user_id, created_at, id),
    KEY entity (entity_type, entity_id),
    CONSTRAINT fk_user_favorites_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
