-- FIREBALL CMS Subscriptions
-- Enable Robokassa receipt nomenclature for existing installations.

UPDATE plugin_settings
SET setting_value = 'true',
    updated_at = NOW()
WHERE plugin_slug = 'subscriptions'
  AND setting_key = 'receipt_enabled';

-- Normalize the old non-Robokassa value used by earlier plugin versions.
UPDATE plugin_settings
SET setting_value = '"full_prepayment"',
    updated_at = NOW()
WHERE plugin_slug = 'subscriptions'
  AND setting_key = 'receipt_payment_method'
  AND setting_value IN ('"prepayment_full"', 'prepayment_full');
