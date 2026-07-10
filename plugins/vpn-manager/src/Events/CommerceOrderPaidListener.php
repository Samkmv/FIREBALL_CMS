<?php

namespace Fireball\VpnManager\Events;

use Fireball\VpnManager\Repositories\VpnRepository;

final class CommerceOrderPaidListener
{
    public static function handle(mixed $payload = null): void
    {
        $data = is_array($payload) ? $payload : [];
        (new VpnRepository())->logEvent('commerce.order.paid.received', 'Commerce order paid event received for future VPN provisioning.', [
            'order_id' => $data['order_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
        ], isset($data['user_id']) ? (int)$data['user_id'] : null);
    }
}
