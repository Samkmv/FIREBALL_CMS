ALTER TABLE subscription_business_pages ADD COLUMN slug VARCHAR(190) NULL;
CREATE UNIQUE INDEX uq_business_slug ON subscription_business_pages (slug);
