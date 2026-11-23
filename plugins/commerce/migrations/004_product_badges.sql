SET @commerce_product_badges_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'commerce_products' AND COLUMN_NAME = 'is_new'),
    'SELECT 1',
    'ALTER TABLE commerce_products ADD COLUMN is_new TINYINT UNSIGNED NOT NULL DEFAULT 0,
        ADD COLUMN compare_at_minor BIGINT UNSIGNED NULL'
);
PREPARE commerce_product_badges_stmt FROM @commerce_product_badges_ddl;
EXECUTE commerce_product_badges_stmt;
DEALLOCATE PREPARE commerce_product_badges_stmt;
