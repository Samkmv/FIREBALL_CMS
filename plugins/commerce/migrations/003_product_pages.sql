SET @commerce_product_specs_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'commerce_products' AND COLUMN_NAME = 'specifications'),
    'SELECT 1',
    'ALTER TABLE commerce_products ADD COLUMN specifications TEXT NULL'
);
PREPARE commerce_product_specs_stmt FROM @commerce_product_specs_ddl;
EXECUTE commerce_product_specs_stmt;
DEALLOCATE PREPARE commerce_product_specs_stmt;

CREATE TABLE IF NOT EXISTS commerce_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY commerce_reviews_author (product_id, user_id),
    KEY commerce_reviews_public (product_id, status, id),
    KEY commerce_reviews_status (status, id),
    CONSTRAINT commerce_reviews_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT commerce_reviews_product FOREIGN KEY (product_id) REFERENCES commerce_products(id) ON DELETE CASCADE,
    CONSTRAINT commerce_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
