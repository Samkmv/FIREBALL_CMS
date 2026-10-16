CREATE TABLE IF NOT EXISTS reel_album_genres (
    album_id BIGINT UNSIGNED NOT NULL,
    genre_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (album_id, genre_id),
    KEY idx_reel_album_genres_genre (genre_id),
    CONSTRAINT fk_reel_album_genres_album FOREIGN KEY (album_id) REFERENCES reel_albums(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_album_genres_genre FOREIGN KEY (genre_id) REFERENCES reel_genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_track_genres (
    track_id BIGINT UNSIGNED NOT NULL,
    genre_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (track_id, genre_id),
    KEY idx_reel_track_genres_genre (genre_id),
    CONSTRAINT fk_reel_track_genres_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_track_genres_genre FOREIGN KEY (genre_id) REFERENCES reel_genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO reel_album_genres (album_id,genre_id) SELECT id,genre_id FROM reel_albums WHERE genre_id IS NOT NULL;
INSERT IGNORE INTO reel_track_genres (track_id,genre_id) SELECT id,genre_id FROM reel_tracks WHERE genre_id IS NOT NULL;
