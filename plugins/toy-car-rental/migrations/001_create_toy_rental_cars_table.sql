CREATE TABLE IF NOT EXISTS toy_rental_cars (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    number VARCHAR(50) NOT NULL,
    color VARCHAR(100) NULL,
    status ENUM('available', 'rented', 'maintenance', 'hidden') NOT NULL DEFAULT 'available',
    price_per_minute DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_per_ride DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image VARCHAR(255) NULL,
    sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY number (number),
    KEY status (status),
    KEY sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
