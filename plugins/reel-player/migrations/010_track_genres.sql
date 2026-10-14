ALTER TABLE reel_tracks ADD COLUMN genre_id BIGINT UNSIGNED NULL;
ALTER TABLE reel_tracks ADD KEY idx_reel_track_genre (genre_id);
ALTER TABLE reel_tracks ADD CONSTRAINT fk_reel_track_genre FOREIGN KEY (genre_id) REFERENCES reel_genres(id) ON DELETE SET NULL;
