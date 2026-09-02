# Calendar

Calendar is a FIREBALL CMS system plugin for personal and shared events. Its management workspace is available at `/admin/calendar` and fills the admin content area beside the standard sidebar. It provides month, week, day, and list views; recurring events; multiple reminders; and independent in-site/PWA Push delivery channels.

## Installation

Install and activate **Calendar** on the FIREBALL CMS plugins page. The plugin manager applies `migrations/001_create_calendar_tables.sql` automatically.

## Reminder worker

The plugin publishes `calendar_dispatch_reminders` to the shared `fireball_scheduled_jobs` contract with a one-minute schedule. On installations without a shared scheduler, run the CLI entry point once per minute:

```cron
* * * * * /usr/bin/php /absolute/path/to/FIREBALL_CMS/plugins/calendar/cron.php
```

Delivery rows are unique per reminder, occurrence, and recipient, so overlapping or repeated worker runs do not send the same reminder twice. Failed deliveries are retried up to four times.

## Channels

- **On site** creates an item in the standard FIREBALL notification center.
- **PWA Push** uses the existing PWA subscriptions and per-user Push preference.
- When both are selected, a single stored notification is created and the standard notification service dispatches its Push counterpart.

PWA Push still requires the global PWA/VAPID settings and the recipient's active browser subscription.

## Verification

Run the isolated recurrence and manifest checks with the system PHP:

```bash
php plugins/calendar/tests/calendar_unit.php
```

The database integration check creates temporary calendar rows and removes them in a `finally` block:

```bash
/Applications/MAMP/bin/php/php8.2.0/bin/php plugins/calendar/tests/calendar_db_integration.php
```
