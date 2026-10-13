CREATE TABLE IF NOT EXISTS reel_album_distribution (
    album_id BIGINT UNSIGNED NOT NULL,
    access_mode VARCHAR(20) NOT NULL DEFAULT 'internal',
    show_metadata TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (album_id),
    KEY idx_reel_album_distribution_access (access_mode, show_metadata, album_id),
    CONSTRAINT fk_reel_ad_album FOREIGN KEY (album_id) REFERENCES reel_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_track_distribution (
    track_id BIGINT UNSIGNED NOT NULL,
    access_mode VARCHAR(20) NOT NULL DEFAULT 'internal',
    show_metadata TINYINT(1) NOT NULL DEFAULT 0,
    rights_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    license_type VARCHAR(40) NOT NULL DEFAULT '',
    license_url VARCHAR(2048) NOT NULL DEFAULT '',
    allow_stream TINYINT(1) NOT NULL DEFAULT 0,
    allow_embed TINYINT(1) NOT NULL DEFAULT 0,
    allow_download TINYINT(1) NOT NULL DEFAULT 0,
    purchase_url VARCHAR(2048) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (track_id),
    KEY idx_reel_track_distribution_access (access_mode, show_metadata, track_id),
    KEY idx_reel_track_distribution_stream (rights_confirmed, allow_stream, track_id),
    CONSTRAINT fk_reel_td_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deliberately no FK to the optional subscriptions plugin: free music also
-- installs and operates when that plugin and its tables do not exist.
CREATE TABLE IF NOT EXISTS reel_album_distribution_plans (
    album_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (album_id, plan_id),
    KEY idx_reel_ad_plan (plan_id, album_id),
    CONSTRAINT fk_reel_adp_album FOREIGN KEY (album_id) REFERENCES reel_albums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reel_track_distribution_plans (
    track_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (track_id, plan_id),
    KEY idx_reel_td_plan (plan_id, track_id),
    CONSTRAINT fk_reel_tdp_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
