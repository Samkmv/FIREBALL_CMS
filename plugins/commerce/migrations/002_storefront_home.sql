CREATE TABLE IF NOT EXISTS commerce_home_slides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    subtitle VARCHAR(160) NOT NULL DEFAULT '',
    button_label VARCHAR(60) NOT NULL DEFAULT '',
    image_path VARCHAR(255) NOT NULL,
    category_id INT UNSIGNED NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY commerce_home_slides_order (is_active, sort_order, id),
    CONSTRAINT commerce_slide_category FOREIGN KEY (category_id) REFERENCES commerce_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @commerce_home_categories_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'commerce_categories' AND COLUMN_NAME = 'image_path'),
    'SELECT 1',
    'ALTER TABLE commerce_categories ADD COLUMN image_path VARCHAR(255) NULL,
        ADD COLUMN revision INT UNSIGNED NOT NULL DEFAULT 1'
);
PREPARE commerce_home_categories_stmt FROM @commerce_home_categories_ddl;
EXECUTE commerce_home_categories_stmt;
DEALLOCATE PREPARE commerce_home_categories_stmt;

SET @commerce_home_products_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'commerce_products' AND COLUMN_NAME = 'is_featured'),
    'SELECT 1',
    'ALTER TABLE commerce_products ADD COLUMN is_featured TINYINT UNSIGNED NOT NULL DEFAULT 0,
        ADD COLUMN hover_image_path VARCHAR(255) NULL,
        ADD COLUMN color_name VARCHAR(60) NOT NULL DEFAULT '''',
        ADD COLUMN color_hex CHAR(7) NOT NULL DEFAULT '''',
        ADD COLUMN variant_group VARCHAR(80) NOT NULL DEFAULT '''',
        ADD KEY commerce_products_variant (variant_group, is_active, id)'
);
PREPARE commerce_home_products_stmt FROM @commerce_home_products_ddl;
EXECUTE commerce_home_products_stmt;
DEALLOCATE PREPARE commerce_home_products_stmt;
