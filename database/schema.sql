CREATE TABLE IF NOT EXISTS user_roles (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    login VARCHAR(120) NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    last_seen_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY email (email),
    UNIQUE KEY login (login),
    KEY role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(10) UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY email (email),
    KEY token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(191) NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_metrics (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_key VARCHAR(120) NOT NULL,
    metric_value BIGINT(20) NOT NULL DEFAULT 0,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY metric_key (metric_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_categories (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    name_ru VARCHAR(255) NULL,
    name_en VARCHAR(255) NULL,
    slug VARCHAR(255) NOT NULL,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    seo_keywords VARCHAR(255) NULL,
    seo_image VARCHAR(255) NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    category VARCHAR(255) NULL,
    category_id INT(10) UNSIGNED NULL,
    excerpt TEXT NULL,
    content LONGTEXT NULL,
    image VARCHAR(255) NULL,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    seo_keywords VARCHAR(255) NULL,
    seo_image VARCHAR(255) NULL,
    hide_placeholder_image TINYINT(1) NOT NULL DEFAULT 0,
    show_on_home TINYINT(1) NOT NULL DEFAULT 0,
    priority INT(10) NOT NULL DEFAULT 0,
    author_id INT(10) UNSIGNED NULL,
    author_name VARCHAR(255) NULL,
    author_role VARCHAR(50) NULL,
    views_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY category_id (category_id),
    KEY is_published (is_published),
    KEY show_on_home (show_on_home)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT NULL,
    seo_title VARCHAR(255) NULL,
    seo_description TEXT NULL,
    seo_keywords VARCHAR(255) NULL,
    seo_image VARCHAR(255) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    show_in_menu TINYINT(1) NOT NULL DEFAULT 0,
    menu_location VARCHAR(50) NULL,
    menu_order INT(10) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_requests (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL,
    viewed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY status (status),
    KEY created_at (created_at)
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
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
