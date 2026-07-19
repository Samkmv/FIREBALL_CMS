# VPN Manager V2 automation

FIREBALL CMS currently uses small job classes as the cron integration contract. Each V2 job exposes a public `handle(): array` method and must be invoked only after the normal CMS bootstrap has loaded the active plugins, database, translations, cache and services.

Registered jobs are available through `FireballPluginVpnManagerV2::jobs()` and the `vpn_manager_v2_jobs` filter:

- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2SyncConfigurationJob` (poll inbounds and clients, validate snapshots, enqueue policy corrections);
- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob`;
- every 10 minutes, after traffic sync: `Fireball\VpnManagerV2\Jobs\VpnV2CheckTrafficLimitsJob`;
- hourly: `Fireball\VpnManagerV2\Jobs\VpnV2CheckExpirationsJob`;
- daily: `Fireball\VpnManagerV2\Jobs\VpnV2SendExpirationNotificationsJob`;
- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2RetryFailedOperationsJob`;
- every minute: `Fireball\VpnManagerV2\Jobs\VpnV2ReconcilePlanSubscriptionsJob`;
- every 10 minutes, offset after discovery: `Fireball\VpnManagerV2\Jobs\VpnV2ProvisionMissingClientsJob`;
- daily: `Fireball\VpnManagerV2\Jobs\VpnV2FullReconcileJob`.

Example after CMS bootstrap:

```php
$result = (new \Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob())->handle();
```

Do not invoke jobs during an ordinary page render. Traffic sync and remote status changes perform network requests to 3x-ui. No HTTP request is held inside a database transaction.

The CMS installation must provide the scheduler/worker that calls these registered contracts after the normal bootstrap. A deployment that only executes web requests will retain queued rows but will not process them. Run a single worker invocation at a time per job schedule; row claims, leases and idempotency make retries safe after a crashed worker.

`VpnV2SyncConfigurationJob` and `VpnV2ProvisionMissingClientsJob` are the frequent incremental path. `VpnV2FullReconcileJob` is the slower safety pass. Manual synchronization buttons enqueue the same operation types and return an operation ID immediately; progress is read from the operation status endpoint.

Notification deduplication is persisted in `vpn_v2_notifications`. The unique key consists of subscription, notification type, occurrence and channel. Profile notifications use the CMS `NotificationService`; it dispatches push only for users who enabled push in the CMS. Email is sent through the CMS `MailService` only when enabled in VPN V2 settings.

Traffic counters are monotonic locally: a temporary API error or a smaller remote counter never replaces a larger confirmed local value. Traffic is never reset by ordinary synchronization.

`reset_traffic` is a separate explicit queued operation. It calls the panel reset endpoint, reads the counter again, and only then resets the local directional and aggregate counters and writes an audit event. It is never inferred from a smaller remote counter.
