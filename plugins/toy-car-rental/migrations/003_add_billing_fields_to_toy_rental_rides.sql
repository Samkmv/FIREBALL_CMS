ALTER TABLE toy_rental_rides
    ADD COLUMN billing_type ENUM('fixed', 'metered') NOT NULL DEFAULT 'fixed' AFTER car_id,
    ADD COLUMN price_per_minute DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER billing_type,
    ADD COLUMN estimated_minutes INT(10) UNSIGNED NULL AFTER price_per_minute,
    ADD COLUMN final_amount DECIMAL(10,2) NULL AFTER payment_amount;

UPDATE toy_rental_rides
SET billing_type = 'fixed',
    final_amount = payment_amount
WHERE billing_type = 'fixed'
  AND final_amount IS NULL;
