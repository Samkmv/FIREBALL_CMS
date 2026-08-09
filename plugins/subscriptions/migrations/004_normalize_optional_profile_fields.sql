UPDATE subscription_profile_fields
SET is_required = 0, updated_at = NOW()
WHERE field_key IN ('first_name', 'last_name', 'middle_name', 'apartment');

UPDATE subscription_profile_fields
SET is_required = 1, is_active = 1, updated_at = NOW()
WHERE field_key IN ('email', 'phone', 'country', 'region', 'city', 'street', 'house', 'postal_code');
