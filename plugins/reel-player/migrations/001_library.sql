CREATE TABLE IF NOT EXISTS reel_tracks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT(10) UNSIGNED NOT NULL,
    title VARCHAR(240) NOT NULL,
    artist VARCHAR(240) NOT NULL DEFAULT '',
    filename VARCHAR(240) NOT NULL,
    file_path VARCHAR(100) NOT NULL,
    mime VARCHAR(80) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    duration DECIMAL(12,3) NOT NULL DEFAULT 0,
    cover_path VARCHAR(100) NULL,
    cover_mime VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reel_tracks_owner (owner_id, id),
    CONSTRAINT fk_reel_tracks_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_playlists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id INT(10) UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reel_playlists_owner (owner_id, id),
    CONSTRAINT fk_reel_playlists_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_playlist_tracks (
    playlist_id BIGINT UNSIGNED NOT NULL,
    track_id BIGINT UNSIGNED NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (playlist_id, track_id),
    KEY idx_reel_playlist_order (playlist_id, position),
    CONSTRAINT fk_reel_pt_playlist FOREIGN KEY (playlist_id) REFERENCES reel_playlists(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_pt_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
