# VPN Manager automation

The CMS does not expose a built-in scheduler in this codebase yet, so VPN Manager keeps automation as job classes that can be called from a cron bootstrap or future scheduler integration.

Recommended schedule:

- Every 10 minutes: `Fireball\VpnManager\Jobs\VpnSyncTrafficJob`
- Every 10 minutes after traffic sync: `Fireball\VpnManager\Jobs\VpnCheckTrafficLimitsJob`
- Every hour: `Fireball\VpnManager\Jobs\VpnCheckExpirationsJob`
- Once per day: `Fireball\VpnManager\Jobs\VpnSendExpirationNotificationsJob`
- Every hour after traffic sync: `Fireball\VpnManager\Jobs\VpnSendTrafficNotificationsJob`
- Every 10 minutes: `Fireball\VpnManager\Jobs\VpnCheckServerStatusJob`

Each job exposes:

```php
$result = (new \Fireball\VpnManager\Jobs\VpnSyncTrafficJob())->handle();
```

Load the CMS bootstrap before calling these classes so database, plugins and translations are available. Do not run these jobs from every admin page load.
