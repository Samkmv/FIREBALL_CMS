CREATE TABLE IF NOT EXISTS commerce_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY commerce_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NULL,
    sku VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    price_minor BIGINT UNSIGNED NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY commerce_products_slug (slug),
    UNIQUE KEY commerce_products_sku (sku),
    KEY commerce_products_catalog (is_active, category_id, id),
    CONSTRAINT commerce_product_category FOREIGN KEY (category_id) REFERENCES commerce_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_settings (
    id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL DEFAULT '',
    introduction VARCHAR(500) NOT NULL DEFAULT '',
    pickup_enabled TINYINT UNSIGNED NOT NULL DEFAULT 1,
    pickup_address VARCHAR(255) NOT NULL DEFAULT '',
    delivery_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,
    delivery_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO commerce_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS commerce_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    checkout_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'new',
    payment_status VARCHAR(12) NOT NULL DEFAULT 'pending',
    customer_name VARCHAR(190) NOT NULL,
    customer_email VARCHAR(190) NOT NULL,
    customer_phone VARCHAR(45) NOT NULL,
    customer_note VARCHAR(1500) NOT NULL DEFAULT '',
    fulfillment VARCHAR(12) NOT NULL,
    fulfillment_address VARCHAR(500) NOT NULL,
    subtotal_minor BIGINT UNSIGNED NOT NULL,
    delivery_minor BIGINT UNSIGNED NOT NULL,
    total_minor BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY commerce_orders_checkout (checkout_key),
    KEY commerce_orders_account (user_id, id),
    KEY commerce_orders_status (status, id),
    CONSTRAINT commerce_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    sku VARCHAR(80) NOT NULL,
    name VARCHAR(190) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_minor BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY commerce_order_items_order (order_id, id),
    CONSTRAINT commerce_item_order FOREIGN KEY (order_id) REFERENCES commerce_orders(id),
    CONSTRAINT commerce_item_product FOREIGN KEY (product_id) REFERENCES commerce_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commerce_order_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NULL,
    event_type VARCHAR(12) NOT NULL,
    new_value VARCHAR(24) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY commerce_events_order (order_id, id),
    CONSTRAINT commerce_event_order FOREIGN KEY (order_id) REFERENCES commerce_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
