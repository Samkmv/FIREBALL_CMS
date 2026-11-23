SET @commerce_gallery_ddl = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'commerce_products' AND COLUMN_NAME = 'gallery_images'),
    'SELECT 1',
    'ALTER TABLE commerce_products ADD COLUMN gallery_images TEXT NULL'
);
PREPARE commerce_gallery_stmt FROM @commerce_gallery_ddl;
EXECUTE commerce_gallery_stmt;
DEALLOCATE PREPARE commerce_gallery_stmt;
