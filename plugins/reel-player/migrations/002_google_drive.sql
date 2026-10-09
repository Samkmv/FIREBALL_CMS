ALTER TABLE reel_tracks MODIFY file_path VARCHAR(100) NULL;
ALTER TABLE reel_tracks ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'local';
ALTER TABLE reel_tracks ADD COLUMN source_id VARCHAR(200) NULL;
ALTER TABLE reel_tracks ADD UNIQUE KEY uq_reel_drive_file (owner_id, source, source_id);
