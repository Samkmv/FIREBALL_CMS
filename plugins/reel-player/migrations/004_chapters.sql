CREATE TABLE IF NOT EXISTS reel_chapters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    track_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(240) NOT NULL,
    artist VARCHAR(240) NOT NULL DEFAULT '',
    start_time DECIMAL(12,6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reel_chapter_start (track_id, start_time),
    CONSTRAINT fk_reel_chapter_track FOREIGN KEY (track_id) REFERENCES reel_tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
