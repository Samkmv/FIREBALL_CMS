CREATE TABLE IF NOT EXISTS reel_albums (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT(10) UNSIGNED NOT NULL,
    title VARCHAR(240) NOT NULL,
    artist VARCHAR(240) NOT NULL DEFAULT '',
    release_date DATE NULL,
    description TEXT NOT NULL,
    cover_path VARCHAR(100) NULL,
    cover_mime VARCHAR(80) NULL,
    published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reel_albums_catalog (published, release_date, id),
    KEY idx_reel_albums_owner (owner_id, id),
    CONSTRAINT fk_reel_album_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_album_tracks (
    album_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (album_id, track_id),
    KEY idx_reel_album_order (album_id, position),
    KEY idx_reel_album_track (track_id, album_id),
    CONSTRAINT fk_reel_at_album FOREIGN KEY (album_id) REFERENCES reel_albums(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_at_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_album_saves (
    owner_id INT(10) UNSIGNED NOT NULL,
    album_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (owner_id, album_id),
    KEY idx_reel_album_saves_album (album_id),
    CONSTRAINT fk_reel_save_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_save_album FOREIGN KEY (album_id) REFERENCES reel_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_catalog_favorites (
    owner_id INT(10) UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    favorite TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (owner_id, track_id),
    KEY idx_reel_catalog_favorite_track (track_id),
    CONSTRAINT fk_reel_cf_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_cf_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_track_stats (
    track_id BIGINT UNSIGNED NOT NULL,
    listens BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (track_id),
    CONSTRAINT fk_reel_stats_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_listening_sessions (
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    owner_id INT(10) UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    threshold DECIMAL(12,3) NOT NULL,
    started_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    counted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (token_hash),
    KEY idx_reel_listening_owner (owner_id, started_at),
    KEY idx_reel_listening_track (owner_id, track_id, started_at),
    KEY idx_reel_listening_expiry (expires_at),
    CONSTRAINT fk_reel_listen_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_listen_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
