ALTER TABLE subscription_content_rules
    MODIFY COLUMN access_mode VARCHAR(30) NOT NULL DEFAULT 'public';

UPDATE subscription_content_rules
SET access_mode = 'public',
    hide_video = 0,
    required_permission = NULL;
