# VPN Manager V2 automation

FIREBALL CMS currently uses small job classes as the cron integration contract. Each V2 job exposes a public `handle(): array` method and must be invoked only after the normal CMS bootstrap has loaded the active plugins, database, translations, cache and services.

Registered jobs are available through `FireballPluginVpnManagerV2::jobs()` and the `vpn_manager_v2_jobs` filter:

- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob`;
- every 10 minutes, after traffic sync: `Fireball\VpnManagerV2\Jobs\VpnV2CheckTrafficLimitsJob`;
- hourly: `Fireball\VpnManagerV2\Jobs\VpnV2CheckExpirationsJob`;
- daily: `Fireball\VpnManagerV2\Jobs\VpnV2SendExpirationNotificationsJob`;
- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2RetryFailedOperationsJob`.

Example after CMS bootstrap:

```php
$result = (new \Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob())->handle();
```

Do not invoke jobs during an ordinary page render. Traffic sync and remote status changes perform network requests to 3x-ui. No HTTP request is held inside a database transaction.

Notification deduplication is persisted in `vpn_v2_notifications`. The unique key consists of subscription, notification type, occurrence and channel. Profile notifications use the CMS `NotificationService`; it dispatches push only for users who enabled push in the CMS. Email is sent through the CMS `MailService` only when enabled in VPN V2 settings.

Traffic counters are monotonic locally: a temporary API error or a smaller remote counter never replaces a larger confirmed local value. There is no automatic traffic reset in Stage 12.
