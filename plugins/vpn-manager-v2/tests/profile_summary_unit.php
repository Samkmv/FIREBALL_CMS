<?php
/** Isolated summary tests; no database, tokens or network calls. */
namespace Fireball\VpnManagerV2\Repositories {
    class ProfileVpnRepository {
        public array $rows = [];
        public function subscriptionsForUser(int $id): array {
            return array_values(array_filter($this->rows, static fn(array $row): bool => $row['user_id'] === $id));
        }
    }
}
namespace Fireball\VpnManagerV2\Services {
    class VpnV2SubscriptionDependencyService {
        public function calculateEffectiveStatus(array $row): array {
            return ['effective_status' => $row['effective'], 'inactive_reason' => $row['reason'] ?? null];
        }
        public function isDependentChild(int $id): bool { return $id === 6; }
    }
}
namespace {
    class FireballPluginVpnManagerV2 { public static function t(string $key): string { return $key; } }
    function base_href(string $path): string { return $path; }
    require __DIR__ . '/../src/Support/ProfileVpnFormatter.php';
    require __DIR__ . '/../src/Support/ProvisioningStatus.php';
    require __DIR__ . '/../src/Support/TrafficFormatter.php';
    require __DIR__ . '/../src/Services/ProfileVpnService.php';
    $repo = new \Fireball\VpnManagerV2\Repositories\ProfileVpnRepository();
    $base = ['user_id' => 10, 'plan_name' => 'VPN Pro', 'plan_description' => 'Private access', 'status' => 'active', 'effective' => 'active', 'expires_at' => null];
    $repo->rows = [
        ['id' => 1] + $base,
        ['id' => 2, 'status' => 'partial_sync'] + $base,
        ['id' => 3, 'effective' => 'inactive', 'reason' => 'subscription_expired'] + $base,
        ['id' => 4, 'effective' => 'inactive', 'reason' => 'subscription_suspended'] + $base,
        ['id' => 5, 'effective' => 'inactive', 'reason' => 'subscription_limit_exceeded'] + $base,
        ['id' => 6] + $base,
        ['id' => 7, 'user_id' => 99] + $base,
    ];
    $service = new \Fireball\VpnManagerV2\Services\ProfileVpnService(repository: $repo, dependencies: new \Fireball\VpnManagerV2\Services\VpnV2SubscriptionDependencyService());
    $cards = $service->profileSubscriptions(10);
    $assert = static function (bool $ok, string $message): void { if (!$ok) { throw new \RuntimeException($message); } };
    $assert(count($cards) === 2, 'Only eligible subscriptions of the requested user are shown');
    $assert($cards[0]['href'] === '/profile/vpn-v2/1', 'Card opens the selected subscription');
    $assert($cards[0]['ends_at'] === null && $cards[0]['plan_name'] === 'VPN Pro', 'Summary keeps real plan and unlimited expiry');
    $assert($cards[1]['status'] === 'partial_sync', 'Sync issues are not labelled active');
    $assert(!isset($cards[0]['subscription_token']) && !isset($cards[0]['subscription_url']), 'No connection credentials exposed');
    $assert($service->profileSubscriptions(0) === [] && $service->profileSubscriptions(123) === [], 'Missing subscriptions produce no cards');
    echo "VPN profile summaries: PASS\n";
}
