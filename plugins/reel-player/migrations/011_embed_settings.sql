CREATE TABLE IF NOT EXISTS reel_embed_settings (
    track_id BIGINT UNSIGNED NOT NULL,
    theme VARCHAR(12) NOT NULL DEFAULT 'system',
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (track_id),
    CONSTRAINT fk_reel_embed_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_embed_domains (
    track_id BIGINT UNSIGNED NOT NULL,
    origin VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    PRIMARY KEY (track_id, origin),
    CONSTRAINT fk_reel_embed_domain FOREIGN KEY (track_id) REFERENCES reel_embed_settings(track_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
