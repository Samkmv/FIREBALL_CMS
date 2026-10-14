CREATE TABLE IF NOT EXISTS reel_genres (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    slug VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY idx_reel_genre_name (name_key),
    UNIQUE KEY idx_reel_genre_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE reel_albums ADD COLUMN genre_id BIGINT UNSIGNED NULL;
ALTER TABLE reel_albums ADD COLUMN moderation_status VARCHAR(16) NOT NULL DEFAULT 'draft';
ALTER TABLE reel_albums ADD COLUMN moderation_note VARCHAR(1000) NOT NULL DEFAULT '';
ALTER TABLE reel_albums ADD COLUMN reviewed_by INT(10) UNSIGNED NULL;
ALTER TABLE reel_albums ADD COLUMN reviewed_at DATETIME NULL;
ALTER TABLE reel_albums ADD KEY idx_reel_album_moderation (published, moderation_status);
ALTER TABLE reel_albums ADD CONSTRAINT fk_reel_album_genre FOREIGN KEY (genre_id) REFERENCES reel_genres(id) ON DELETE SET NULL;
UPDATE reel_albums SET moderation_status='pending' WHERE published=1;
ALTER TABLE reel_tracks ADD COLUMN album_title VARCHAR(240) NOT NULL DEFAULT '';
ALTER TABLE reel_tracks ADD COLUMN genre_tag VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE reel_tracks ADD COLUMN metadata_loaded TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE reel_tracks ADD COLUMN album_artist VARCHAR(240) NOT NULL DEFAULT '';
ALTER TABLE reel_albums ADD COLUMN moderation_version INT UNSIGNED NOT NULL DEFAULT 0;
