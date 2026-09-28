-- Separate the historical IP policy from device_limit (HWID). Local only; replay safe.

SET @vpn_ip_column = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_plans' AND COLUMN_NAME = 'ip_limit'),
 'SELECT 1', 'ALTER TABLE vpn_v2_plans ADD COLUMN ip_limit INT UNSIGNED NULL DEFAULT NULL AFTER device_limit');
PREPARE vpn_ip_stmt FROM @vpn_ip_column;
EXECUTE vpn_ip_stmt;
DEALLOCATE PREPARE vpn_ip_stmt;
UPDATE vpn_v2_plans SET ip_limit = device_limit WHERE ip_limit IS NULL;
ALTER TABLE vpn_v2_plans MODIFY COLUMN ip_limit INT UNSIGNED NOT NULL DEFAULT 0;

SET @vpn_ip_column = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vpn_v2_subscriptions' AND COLUMN_NAME = 'ip_limit'),
 'SELECT 1', 'ALTER TABLE vpn_v2_subscriptions ADD COLUMN ip_limit INT UNSIGNED NULL DEFAULT NULL AFTER device_limit');
PREPARE vpn_ip_stmt FROM @vpn_ip_column;
EXECUTE vpn_ip_stmt;
DEALLOCATE PREPARE vpn_ip_stmt;
UPDATE vpn_v2_subscriptions SET ip_limit = device_limit WHERE ip_limit IS NULL;
ALTER TABLE vpn_v2_subscriptions MODIFY COLUMN ip_limit INT UNSIGNED NOT NULL DEFAULT 0;
