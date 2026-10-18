CREATE TABLE IF NOT EXISTS reel_social_profiles (
    owner_id INT(10) UNSIGNED NOT NULL,
    bio VARCHAR(500) NOT NULL DEFAULT '',
    profile_public TINYINT(1) NOT NULL DEFAULT 1,
    collection_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (owner_id),
    CONSTRAINT fk_reel_social_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_user_follows (
    follower_id INT(10) UNSIGNED NOT NULL,
    followed_id INT(10) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (follower_id, followed_id),
    KEY idx_reel_followed (followed_id, follower_id),
    CONSTRAINT fk_reel_follower FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_followed FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
