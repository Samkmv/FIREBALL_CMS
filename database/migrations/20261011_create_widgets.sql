CREATE TABLE IF NOT EXISTS widget_instances (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(100) NOT NULL,
    area VARCHAR(100) NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    settings MEDIUMTEXT NOT NULL,
    enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
    visibility TEXT NOT NULL,
    system_key VARCHAR(100) NULL,
    UNIQUE KEY widget_system_key (system_key),
    KEY widget_area_order (area, position, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS widget_state (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    revision INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO widget_state (id, revision) VALUES (1, 1);
INSERT IGNORE INTO widget_instances (type, area, position, settings, enabled, visibility, system_key) VALUES
('posts.categories', 'posts.sidebar', 0, '{}', 1, '{"posts.index":true,"posts.show":true}', 'posts.categories'),
('posts.trending', 'posts.sidebar', 1, '{}', 1, '{"posts.index":true,"posts.show":true}', 'posts.trending'),
('posts.social', 'posts.sidebar', 2, '{}', 1, '{"posts.index":true,"posts.show":true}', 'posts.social');
