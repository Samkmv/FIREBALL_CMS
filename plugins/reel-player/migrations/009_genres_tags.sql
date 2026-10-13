CREATE TABLE IF NOT EXISTS reel_genres (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_reel_genre_name (name_key),
    UNIQUE KEY idx_reel_genre_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_tags (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY idx_reel_tag_name (name_key),
    UNIQUE KEY idx_reel_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_track_genres (
    track_id BIGINT UNSIGNED NOT NULL,
    genre_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (track_id, genre_id),
    KEY idx_reel_genre_tracks (genre_id, track_id),
    CONSTRAINT fk_reel_tg_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_tg_genre FOREIGN KEY (genre_id) REFERENCES reel_genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_album_genres (
    album_id BIGINT UNSIGNED NOT NULL,
    genre_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (album_id, genre_id),
    KEY idx_reel_genre_albums (genre_id, album_id),
    CONSTRAINT fk_reel_ag_album FOREIGN KEY (album_id) REFERENCES reel_albums(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_ag_genre FOREIGN KEY (genre_id) REFERENCES reel_genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_track_tags (
    track_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (track_id, tag_id),
    KEY idx_reel_tag_tracks (tag_id, track_id),
    CONSTRAINT fk_reel_tt_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_tt_tag FOREIGN KEY (tag_id) REFERENCES reel_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_album_tags (
    album_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (album_id, tag_id),
    KEY idx_reel_tag_albums (tag_id, album_id),
    CONSTRAINT fk_reel_atag_album FOREIGN KEY (album_id) REFERENCES reel_albums(id) ON DELETE CASCADE,
    CONSTRAINT fk_reel_atag_tag FOREIGN KEY (tag_id) REFERENCES reel_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
