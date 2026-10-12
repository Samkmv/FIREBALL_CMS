ALTER TABLE reel_listening_sessions ADD COLUMN credited TINYINT(1) NOT NULL DEFAULT 0 AFTER counted;
