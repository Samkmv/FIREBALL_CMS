# VPN Manager Plugin API

The plugin is brand-neutral. Set the public service name in `VPN -> Settings`.

Commerce integrations should emit an event after successful payment:

```php
fireball_event('commerce.order.paid', [
    'order_id' => $orderId,
    'user_id' => $userId,
    'product_id' => $productId,
]);
```

VPN Manager listens to this event and records it for future provisioning. The first stage does not create 3x-ui clients automatically yet.

Use CMS notifications through `NotificationService::create()` or the helper:

```php
notification_create([
    'user_id' => $userId,
    'title' => 'VPN subscription reminder',
    'message' => 'Your VPN subscription expires soon.',
    'type' => 'vpn_expiration',
    'action_url' => '/my-vpn',
    'source' => 'vpn-manager',
]);
```
