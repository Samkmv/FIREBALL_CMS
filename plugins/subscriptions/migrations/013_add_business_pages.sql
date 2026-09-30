CREATE TABLE IF NOT EXISTS subscription_business_pages (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id INT UNSIGNED NOT NULL,
 name VARCHAR(190) NOT NULL,
 description TEXT NOT NULL,
 address VARCHAR(255) NOT NULL DEFAULT '',
 phone VARCHAR(50) NOT NULL DEFAULT '',
 email VARCHAR(190) NOT NULL DEFAULT '',
 website VARCHAR(500) NOT NULL DEFAULT '',
 hours VARCHAR(255) NOT NULL DEFAULT '',
 socials_json TEXT NOT NULL,
 avatar VARCHAR(500) NOT NULL DEFAULT '',
 cover VARCHAR(500) NOT NULL DEFAULT '',
 is_published TINYINT(1) NOT NULL DEFAULT 0,
 camera_id INT UNSIGNED NULL,
 camera_title VARCHAR(190) NOT NULL DEFAULT '',
 show_camera TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_business_owner (user_id),
 UNIQUE KEY uq_business_camera (camera_id),
 KEY idx_business_public (is_published, id),
 CONSTRAINT fk_business_owner FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_business_posts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 business_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(20) NOT NULL,
 title VARCHAR(190) NOT NULL,
 body TEXT NOT NULL,
 image VARCHAR(500) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (id), KEY idx_business_posts (business_id, id),
 CONSTRAINT fk_business_post FOREIGN KEY (business_id) REFERENCES subscription_business_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_business_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 business_id BIGINT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 reply TEXT NOT NULL,
 is_hidden TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_business_review (business_id, user_id),
 KEY idx_business_reviews (business_id, is_hidden, id),
 CONSTRAINT fk_business_review_page FOREIGN KEY (business_id) REFERENCES subscription_business_pages(id) ON DELETE CASCADE,
 CONSTRAINT fk_business_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
