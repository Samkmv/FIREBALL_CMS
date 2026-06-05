INSERT INTO user_roles (id, name, slug, is_system, created_at) VALUES
(1, 'Creator', 'creator', 1, :now),
(2, 'Admin', 'admin', 1, :now),
(3, 'Moderator', 'moderator', 1, :now),
(4, 'User', 'user', 1, :now)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_system = VALUES(is_system);

INSERT INTO site_metrics (metric_key, metric_value, updated_at) VALUES
('site_visits', 0, :now),
('page_views', 0, :now)
ON DUPLICATE KEY UPDATE metric_value = VALUES(metric_value), updated_at = VALUES(updated_at);
