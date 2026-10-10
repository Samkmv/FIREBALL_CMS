# VPN Manager V2 automation

FIREBALL CMS currently uses small job classes as the cron integration contract. Each V2 job exposes a public `handle(): array` method and must be invoked only after the normal CMS bootstrap has loaded the active plugins, database, translations, cache and services.

Registered jobs are available through `FireballPluginVpnManagerV2::jobs()`, the CMS-wide `fireball_scheduled_jobs` filter and the backwards-compatible `vpn_manager_v2_jobs` filter:

- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2SyncConfigurationJob` (poll inbounds and clients, validate snapshots, enqueue policy corrections);
- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob`;
- every 10 minutes, after traffic sync: `Fireball\VpnManagerV2\Jobs\VpnV2CheckTrafficLimitsJob`;
- hourly: `Fireball\VpnManagerV2\Jobs\VpnV2CheckExpirationsJob`;
- daily: `Fireball\VpnManagerV2\Jobs\VpnV2SendExpirationNotificationsJob`;
- every 10 minutes: `Fireball\VpnManagerV2\Jobs\VpnV2RetryFailedOperationsJob`;
- every minute: `Fireball\VpnManagerV2\Jobs\VpnV2ReconcilePlanSubscriptionsJob`;
- every minute: `Fireball\VpnManagerV2\Jobs\VpnV2ServerHealthJob` (opt-in, one due server per run, diagnostics only);
- every 10 minutes, offset after discovery: `Fireball\VpnManagerV2\Jobs\VpnV2ProvisionMissingClientsJob`;
- daily: `Fireball\VpnManagerV2\Jobs\VpnV2FullReconcileJob`.

Example after CMS bootstrap:

```php
$result = (new \Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob())->handle();
```

Do not invoke jobs during an ordinary page render. Traffic sync and remote status changes perform network requests to 3x-ui. No HTTP request is held inside a database transaction.

The CMS installation must provide the scheduler/worker that calls these registered contracts after the normal bootstrap. A deployment that only executes web requests will retain queued rows but will not process them. Run a single worker invocation at a time per job schedule; row claims, leases and idempotency make retries safe after a crashed worker.

For installations without a shared CMS scheduler, run the CLI-only `plugins/vpn-manager-v2/cron.php` every minute. It first disables expired subscriptions (including retries after a failed remote update), then maintains notifications. The notification outbox deduplicates each subscription, event and channel; the process lock prevents overlapping runs. Remote failures produce `partial_failure` and exit code 1, rather than a successful cron result. `--access-only` runs expiration enforcement without notification maintenance; `--notifications-only` retains the previous notification-only behavior. Expiration failures may still generate critical alerts if enabled in the plugin settings. This entry point does not replace the configuration and traffic synchronization jobs listed above.

The public subscription endpoint checks expiration on every request, independently of cron and panel connectivity. By default, an inactive subscription returns HTTP 200 with one unusable VLESS placeholder named `⛔ VPN-подписка неактивна`. This lets VPN apps replace their previously downloaded servers. The response bypasses cached configurations and conditional 304 handling, is marked `no-store`, and contains the real expiration metadata. Historical `gone` settings upgrade to this behavior automatically; an explicitly selected `not_found` mode still returns HTTP 404 and may leave old configurations in the app. Renewal restores real servers through the same subscription URL. This refresh behavior does not revoke credentials already saved elsewhere: remote expiration/disable and working panel authorization are still required.

`VpnV2SyncConfigurationJob` and `VpnV2ProvisionMissingClientsJob` are the frequent incremental path. `VpnV2FullReconcileJob` is the slower safety pass. An explicit manual synchronization POST enqueues and immediately claims its exact operation, so an installation without a running cron does not leave administrator actions permanently pending. The operation status endpoint returns both the stable technical code and its localized display label. The operations page also provides an explicit recovery action that processes a bounded batch of older due rows. Scheduled workers remain the fallback for retries and unattended work.

Notification deduplication is persisted in `vpn_v2_notifications`. The unique key consists of subscription, notification type, occurrence and channel. Profile notifications use the CMS `NotificationService`; it dispatches push only for users who enabled push in the CMS. Email is sent through the CMS `MailService` only when enabled in VPN V2 settings.

Expiration enforcement and reminders include every subscription state that can still deliver access: `active`, `partial_sync`, and `sync_error`. A partially synchronized subscription therefore cannot silently miss its reminder or remain usable after its expiration time.

Traffic counters are monotonic locally: a temporary API error or a smaller remote counter never replaces a larger confirmed local value. Traffic is never reset by ordinary synchronization.

`reset_traffic` is a separate explicit queued operation. It calls the panel reset endpoint, reads the counter again, and only then resets the local directional and aggregate counters and writes an audit event. It is never inferred from a smaller remote counter.

Dependency-aware workers also process `cascade_disable_children`,
`cascade_enable_children`, `detach_child_subscription`,
`detach_child_connection`, and `recalculate_effective_status`. Expiration and
traffic-limit jobs block merged local delivery before contacting any panel. A
failed child update is retried by the persistent operation queue; successful
updates are not rolled back. The daily full reconciliation recalculates all
stored effective statuses after configuration and plan reconciliation.
It also refreshes a bounded batch of enabled external subscription URLs. Failed
refreshes record `sync_error` without erasing the previous confirmed snapshot.

Smart Connect infrastructure diagnostics can also run through `cron.php --health-only`. The normal CLI cron includes this job when monitoring is enabled. No health checks are attached to web requests. See [Smart Connect](smart-connect.md) for client-side selection, limits and administrator setup.
