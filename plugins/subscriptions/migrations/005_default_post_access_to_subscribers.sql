ALTER TABLE subscription_content_rules
    MODIFY COLUMN access_mode VARCHAR(30) NOT NULL DEFAULT 'subscribers';
