CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    price_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'RUB',
    duration_unit VARCHAR(20) NOT NULL DEFAULT 'days',
    duration_value INT UNSIGNED NOT NULL DEFAULT 30,
    grace_period_days INT UNSIGNED NOT NULL DEFAULT 0,
    is_recurring TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    is_public TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_plans_slug (slug),
    KEY idx_subscription_plans_public (is_active, is_public, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plan_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    permission_value TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_plan_permission (plan_id, permission_key),
    KEY idx_subscription_permission_key (permission_key),
    CONSTRAINT fk_subscription_permission_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plan_resources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_id INT UNSIGNED NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id VARCHAR(190) NOT NULL,
    can_view TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    can_download TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    archive_days INT UNSIGNED NULL,
    max_fragment_minutes INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_plan_resource (plan_id, resource_type, resource_id),
    KEY idx_subscription_resource_lookup (resource_type, resource_id),
    CONSTRAINT fk_subscription_resource_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    middle_name VARCHAR(100) NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    phone VARCHAR(50) NOT NULL DEFAULT '',
    country VARCHAR(120) NOT NULL DEFAULT '',
    region VARCHAR(190) NOT NULL DEFAULT '',
    city VARCHAR(190) NOT NULL DEFAULT '',
    street VARCHAR(190) NOT NULL DEFAULT '',
    house VARCHAR(50) NOT NULL DEFAULT '',
    apartment VARCHAR(50) NULL,
    postal_code VARCHAR(30) NOT NULL DEFAULT '',
    email_verified_at DATETIME NULL,
    phone_verified_at DATETIME NULL,
    data_completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_profiles_user (user_id),
    KEY idx_subscription_profiles_completed (data_completed_at),
    CONSTRAINT fk_subscription_profiles_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_profile_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_key VARCHAR(120) NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT NULL,
    placeholder VARCHAR(255) NULL,
    field_type VARCHAR(30) NOT NULL DEFAULT 'text',
    is_required TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    is_system TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    is_editable TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    show_during_checkout TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    use_in_receipt TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    validation_rules TEXT NULL,
    options_json MEDIUMTEXT NULL,
    plan_ids_json TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_profile_fields_key (field_key),
    KEY idx_subscription_profile_fields_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_profile_values (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    profile_id BIGINT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    field_value MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_profile_value (profile_id, field_id),
    KEY idx_subscription_profile_values_field (field_id),
    CONSTRAINT fk_subscription_value_profile FOREIGN KEY (profile_id)
        REFERENCES subscription_profiles(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_subscription_value_field FOREIGN KEY (field_id)
        REFERENCES subscription_profile_fields(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    grace_ends_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    next_billing_at DATETIME NULL,
    auto_renew TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    parent_payment_id BIGINT UNSIGNED NULL,
    admin_comment TEXT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'robokassa',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_subscriptions_user_status (user_id, status, ends_at),
    KEY idx_subscriptions_plan (plan_id),
    KEY idx_subscriptions_renewal (auto_renew, next_billing_at),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    plan_snapshot MEDIUMTEXT NOT NULL,
    customer_snapshot MEDIUMTEXT NOT NULL,
    consent_snapshot TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'created',
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_orders_invoice (invoice_id),
    KEY idx_subscription_orders_user (user_id, created_at),
    KEY idx_subscription_orders_status (status, expires_at),
    CONSTRAINT fk_subscription_orders_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_orders_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_orders_subscription FOREIGN KEY (subscription_id)
        REFERENCES subscriptions(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'robokassa',
    invoice_id BIGINT UNSIGNED NOT NULL,
    parent_payment_id BIGINT UNSIGNED NULL,
    parent_invoice_id BIGINT UNSIGNED NULL,
    amount_minor BIGINT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'created',
    payment_type VARCHAR(30) NOT NULL DEFAULT 'initial',
    billing_period_start DATETIME NULL,
    signature_verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    provider_payload MEDIUMTEXT NULL,
    provider_transaction VARCHAR(190) NULL,
    error_message TEXT NULL,
    paid_at DATETIME NULL,
    failed_at DATETIME NULL,
    cleared_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_payment_invoice (provider, invoice_id),
    KEY idx_subscription_payments_user (user_id, created_at),
    KEY idx_subscription_payments_status (status, created_at),
    KEY idx_subscription_payments_visible (cleared_at, created_at),
    KEY idx_subscription_payments_parent (parent_payment_id),
    UNIQUE KEY uq_subscription_recurring_period (subscription_id, payment_type, billing_period_start),
    CONSTRAINT fk_subscription_payments_order FOREIGN KEY (order_id)
        REFERENCES subscription_orders(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_payments_subscription FOREIGN KEY (subscription_id)
        REFERENCES subscriptions(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscription_payments_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_payments_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_subscription_payments_parent FOREIGN KEY (parent_payment_id)
        REFERENCES subscription_payments(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_content_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_type VARCHAR(50) NOT NULL,
    content_id VARCHAR(190) NOT NULL,
    access_mode VARCHAR(30) NOT NULL DEFAULT 'subscribers',
    show_title TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    show_excerpt TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    show_image TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    hide_video TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    required_permission VARCHAR(120) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_content_rule (content_type, content_id),
    KEY idx_subscription_content_access (access_mode),
    KEY idx_subscription_content_permission (required_permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_content_plans (
    content_rule_id BIGINT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (content_rule_id, plan_id),
    KEY idx_subscription_content_plans_plan (plan_id),
    CONSTRAINT fk_subscription_content_plan_rule FOREIGN KEY (content_rule_id)
        REFERENCES subscription_content_rules(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_subscription_content_plan_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    event_hash CHAR(64) NOT NULL,
    signature_verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    processing_status VARCHAR(30) NOT NULL DEFAULT 'received',
    payload MEDIUMTEXT NULL,
    error_message TEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscription_webhook_hash (provider, event_hash),
    KEY idx_subscription_webhook_invoice (provider, invoice_id),
    KEY idx_subscription_webhook_status (processing_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_id BIGINT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    actor_user_id INT UNSIGNED NULL,
    event_key VARCHAR(120) NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    metadata MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_subscription_events_subscription (subscription_id, created_at),
    KEY idx_subscription_events_payment (payment_id),
    KEY idx_subscription_events_user (user_id, created_at),
    KEY idx_subscription_events_key (event_key, created_at),
    CONSTRAINT fk_subscription_events_subscription FOREIGN KEY (subscription_id)
        REFERENCES subscriptions(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscription_events_payment FOREIGN KEY (payment_id)
        REFERENCES subscription_payments(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscription_events_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_subscription_events_actor FOREIGN KEY (actor_user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO subscription_profile_fields
    (field_key, label, field_type, is_required, is_active, is_system, is_editable, show_during_checkout, use_in_receipt, sort_order, created_at, updated_at)
VALUES
    ('first_name', 'First name', 'text', 0, 1, 1, 1, 1, 1, 10, NOW(), NOW()),
    ('last_name', 'Last name', 'text', 0, 1, 1, 1, 1, 1, 20, NOW(), NOW()),
    ('middle_name', 'Middle name', 'text', 0, 1, 1, 1, 1, 1, 30, NOW(), NOW()),
    ('email', 'Email', 'email', 1, 1, 1, 1, 1, 1, 40, NOW(), NOW()),
    ('phone', 'Phone', 'phone', 1, 1, 1, 1, 1, 1, 50, NOW(), NOW()),
    ('country', 'Country', 'text', 1, 1, 1, 1, 1, 1, 60, NOW(), NOW()),
    ('region', 'Region', 'text', 1, 1, 1, 1, 1, 1, 70, NOW(), NOW()),
    ('city', 'City', 'text', 1, 1, 1, 1, 1, 1, 80, NOW(), NOW()),
    ('street', 'Street', 'text', 1, 1, 1, 1, 1, 1, 90, NOW(), NOW()),
    ('house', 'House', 'text', 1, 1, 1, 1, 1, 1, 100, NOW(), NOW()),
    ('apartment', 'Apartment / office', 'text', 0, 1, 1, 1, 1, 1, 110, NOW(), NOW()),
    ('postal_code', 'Postal code', 'text', 1, 1, 1, 1, 1, 1, 120, NOW(), NOW());
