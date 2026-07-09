CREATE TABLE IF NOT EXISTS user_roles (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL,
    is_system TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_enabled_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY login (login),
    UNIQUE KEY email (email),
    KEY role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY token_hash (token_hash),
    KEY user_id (user_id),
    KEY email (email),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_metrics (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_key VARCHAR(100) NOT NULL,
    metric_value BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY metric_key (metric_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NULL,
    type VARCHAR(80) NOT NULL DEFAULT 'system',
    action_url VARCHAR(500) NULL,
    icon VARCHAR(500) NULL,
    source VARCHAR(120) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    metadata MEDIUMTEXT NULL,
    is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY user_unread (user_id, is_read, created_at),
    KEY type (type),
    KEY source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_settings (
    user_id INT(10) UNSIGNED NOT NULL,
    push_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pwa_subscriptions (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NULL,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    endpoint_hash CHAR(64) NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh TEXT NULL,
    auth TEXT NULL,
    platform VARCHAR(60) NULL,
    browser VARCHAR(60) NULL,
    user_agent VARCHAR(255) NULL,
    subscription_json MEDIUMTEXT NOT NULL,
    last_seen_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY endpoint_hash (endpoint_hash),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pwa_notifications (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NULL,
    user_id INT(10) UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    type VARCHAR(80) NULL,
    source VARCHAR(120) NULL,
    payload MEDIUMTEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    sent_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY notification_id (notification_id),
    KEY user_id (user_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_categories (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    name_ru VARCHAR(150) NULL,
    name_en VARCHAR(150) NULL,
    slug VARCHAR(180) NOT NULL,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    seo_keywords TEXT NULL,
    seo_image VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    category VARCHAR(150) NOT NULL,
    category_id INT(10) UNSIGNED NULL,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    image VARCHAR(255) NULL,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    seo_keywords TEXT NULL,
    seo_image VARCHAR(255) NULL,
    hide_placeholder_image TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    show_on_home TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    priority INT(10) UNSIGNED NOT NULL DEFAULT 0,
    author_id INT(10) UNSIGNED NULL,
    author_name VARCHAR(100) NULL,
    author_role VARCHAR(20) NOT NULL DEFAULT 'user',
    views_count INT(10) UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME NOT NULL,
    is_published TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY published_at (published_at),
    KEY category_id (category_id),
    KEY published_lookup (is_published, published_at, id),
    KEY category_published (category_id, is_published, published_at),
    KEY show_on_home (show_on_home),
    KEY priority (priority),
    KEY home_featured (is_published, show_on_home, priority, published_at),
    KEY popular_published (is_published, views_count, priority, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    menu_title VARCHAR(255) NULL,
    slug VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    is_published TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    show_in_header TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    show_in_footer TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    show_in_legal_information TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    menu_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY published_order (is_published, menu_order, title),
    KEY header_order (show_in_header, is_published, menu_order),
    KEY footer_order (show_in_footer, is_published, menu_order),
    KEY show_in_legal_information (show_in_legal_information, is_published, menu_order, title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(50) NOT NULL DEFAULT '',
    subject VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    is_viewed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY created_at (created_at),
    KEY is_viewed (is_viewed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_subjects (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    recipient_email VARCHAR(190) NULL,
    responsible_user_id INT(10) UNSIGNED NULL,
    category VARCHAR(100) NULL,
    auto_reply TEXT NULL,
    color_label VARCHAR(32) NULL,
    priority TINYINT(3) UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY name (name),
    KEY active_sort (is_active, sort_order, id),
    KEY responsible_user_id (responsible_user_id),
    KEY category (category),
    KEY priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_visits (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(128) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    country VARCHAR(120) NULL,
    country_code VARCHAR(8) NULL,
    city VARCHAR(120) NULL,
    device_type VARCHAR(20) NOT NULL DEFAULT 'Desktop',
    os VARCHAR(40) NOT NULL DEFAULT 'Other',
    browser VARCHAR(40) NOT NULL DEFAULT 'Other',
    referer TEXT NULL,
    source VARCHAR(80) NOT NULL DEFAULT 'Direct',
    landing_page VARCHAR(2048) NOT NULL,
    current_page VARCHAR(2048) NOT NULL,
    utm_source VARCHAR(255) NULL,
    utm_medium VARCHAR(255) NULL,
    utm_campaign VARCHAR(255) NULL,
    utm_content VARCHAR(255) NULL,
    utm_term VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_created_at (created_at),
    KEY idx_session_created (session_id, created_at),
    KEY idx_source_created (source, created_at),
    KEY idx_country_created (country_code, created_at),
    KEY idx_device_created (device_type, created_at),
    KEY idx_os_created (os, created_at),
    KEY idx_browser_created (browser, created_at),
    KEY idx_page_created (current_page(191), created_at),
    KEY idx_utm_source_created (utm_source(120), created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_id INT(10) UNSIGNED NOT NULL,
    receiver_id INT(10) UNSIGNED NOT NULL,
    message_ciphertext MEDIUMTEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_type VARCHAR(120) NULL,
    attachment_size INT(10) UNSIGNED NULL,
    sender_ip VARCHAR(64) NULL,
    sender_user_agent VARCHAR(255) NULL,
    is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    deleted_by INT(10) UNSIGNED NULL,
    deleted_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY sender_id (sender_id),
    KEY receiver_id (receiver_id),
    KEY conversation_pair (sender_id, receiver_id),
    KEY deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action VARCHAR(50) NOT NULL,
    actor_user_id INT(10) UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    conversation_first_user_id INT(10) UNSIGNED NULL,
    conversation_second_user_id INT(10) UNSIGNED NULL,
    details_json LONGTEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY actor_user_id (actor_user_id),
    KEY message_id (message_id),
    KEY conversation_pair (conversation_first_user_id, conversation_second_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) DEFAULT NULL,
    parent_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) DEFAULT NULL,
    category_id INT(10) UNSIGNED NOT NULL,
    price INT(10) UNSIGNED NOT NULL,
    old_price INT(10) UNSIGNED NOT NULL DEFAULT 0,
    excerpt VARCHAR(255) DEFAULT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    gallery TEXT DEFAULT NULL,
    is_sale TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    in_stock TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS database_maintenance_logs (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NULL,
    user_name VARCHAR(255) NULL,
    ip_address VARCHAR(64) NULL,
    action VARCHAR(100) NOT NULL,
    result VARCHAR(20) NOT NULL,
    error TEXT NULL,
    backup_path VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY action (action),
    KEY result (result),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS update_migrations (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    executed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_update_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NULL,
    user_name VARCHAR(255) NULL,
    from_version VARCHAR(50) NOT NULL,
    to_version VARCHAR(50) NULL,
    result VARCHAR(20) NOT NULL,
    error TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY result (result),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugins (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(255) NOT NULL,
    version VARCHAR(50) NOT NULL,
    description TEXT NULL,
    author VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'inactive',
    installed_at DATETIME NULL,
    activated_at DATETIME NULL,
    deactivated_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_migrations (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    plugin_slug VARCHAR(120) NOT NULL,
    migration VARCHAR(255) NOT NULL,
    executed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY plugin_migration (plugin_slug, migration),
    KEY plugin_slug (plugin_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plugin_settings (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    plugin_slug VARCHAR(120) NOT NULL,
    setting_key VARCHAR(190) NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY plugin_setting (plugin_slug, setting_key),
    KEY plugin_slug (plugin_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
