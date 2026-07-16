<?php

use FBL\Plugins\PluginInterface;
use Fireball\VpnManagerV2\Repositories\ProfileVpnRepository;
use Fireball\VpnManagerV2\Jobs\VpnV2CheckExpirationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2CheckTrafficLimitsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2RetryFailedOperationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2ReconcilePlanSubscriptionsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2SendExpirationNotificationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\VpnV2SchemaUpgradeService;
use Fireball\VpnManagerV2\Support\Permissions;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

final class FireballPluginVpnManagerV2 implements PluginInterface
{
    public const SLUG = 'vpn-manager-v2';

    public function install(): void
    {
        (new SettingsService())->ensureDefaults();
        fireball_event('vpn_manager_v2.installed', [
            'slug' => self::SLUG,
            'permissions' => array_keys(self::permissions()),
        ]);
    }

    public function uninstall(): void
    {
        // V2 data is retained intentionally. Destructive uninstall belongs to a later explicit workflow.
    }

    public function activate(): void
    {
        (new SettingsService())->ensureDefaults();
        fireball_event('vpn_manager_v2.activated', ['slug' => self::SLUG]);
    }

    public function deactivate(): void
    {
        fireball_event('vpn_manager_v2.deactivated', ['slug' => self::SLUG]);
    }

    public function boot(): void
    {
        (new VpnV2SchemaUpgradeService())->ensureCurrent();

        add_filter('admin_menu', static function (array $menu): array {
            $menu[] = [
                'group' => 'applications',
                'label' => self::t('vpn_manager_v2_menu'),
                'href' => base_href('/admin/plugins/vpn-manager-v2'),
                'icon' => 'ci-server',
                'plugin_menu' => true,
                'order' => 81,
            ];

            return $menu;
        });

        add_filter('vpn_manager_v2_permissions', static function (array $permissions): array {
            return array_replace($permissions, Permissions::definitions());
        });

        add_filter('vpn_manager_v2_jobs', static function (array $jobs): array {
            return array_replace($jobs, self::jobs());
        });

        add_filter('profile_menu', static function (array $items, array $user = []): array {
            $userId = (int)($user['id'] ?? 0);
            try {
                $settings = (new SettingsService())->current();
                if ($userId <= 0 || empty($settings['public_account_enabled'])
                    || !(new ProfileVpnRepository())->hasSubscriptionsForUser($userId)) {
                    return $items;
                }
                $items[] = [
                    'key' => 'vpn-v2',
                    'label' => self::t('vpn_manager_v2_profile_menu'),
                    'href' => base_href('/profile/vpn-v2'),
                    'icon' => 'ci-server',
                    'order' => 81,
                    'plugin' => self::SLUG,
                ];
            } catch (\Throwable $exception) {
                error_log('VPN Manager V2 profile menu failed: ' . get_class($exception));
            }

            return $items;
        }, 10);
    }

    public static function t(string $key): string
    {
        return return_translation($key);
    }

    public static function permissions(): array
    {
        return Permissions::definitions();
    }

    public static function jobs(): array
    {
        return [
            'vpn_v2_sync_traffic' => [
                'class' => VpnV2SyncTrafficJob::class,
                'schedule' => '*/10 * * * *',
            ],
            'vpn_v2_check_traffic_limits' => [
                'class' => VpnV2CheckTrafficLimitsJob::class,
                'schedule' => '5-59/10 * * * *',
            ],
            'vpn_v2_check_expirations' => [
                'class' => VpnV2CheckExpirationsJob::class,
                'schedule' => '15 * * * *',
            ],
            'vpn_v2_send_expiration_notifications' => [
                'class' => VpnV2SendExpirationNotificationsJob::class,
                'schedule' => '10 9 * * *',
            ],
            'vpn_v2_retry_failed_operations' => [
                'class' => VpnV2RetryFailedOperationsJob::class,
                'schedule' => '*/10 * * * *',
            ],
            'vpn_v2_reconcile_plan_subscriptions' => [
                'class' => VpnV2ReconcilePlanSubscriptionsJob::class,
                'schedule' => '* * * * *',
            ],
        ];
    }

    public static function tabs(string $active): array
    {
        $items = [
            'overview' => ['vpn_manager_v2_tab_overview', '/admin/plugins/vpn-manager-v2', 'ci-layout'],
            'servers' => ['vpn_manager_v2_tab_servers', '/admin/plugins/vpn-manager-v2/servers', 'ci-server'],
            'inbounds' => ['vpn_manager_v2_tab_inbounds', '/admin/plugins/vpn-manager-v2/inbounds', 'ci-log-in'],
            'plans' => ['vpn_manager_v2_tab_plans', '/admin/plugins/vpn-manager-v2/plans', 'ci-package'],
            'subscriptions' => ['vpn_manager_v2_tab_subscriptions', '/admin/plugins/vpn-manager-v2/subscriptions', 'ci-link'],
            'connections' => ['vpn_manager_v2_tab_connections', '/admin/plugins/vpn-manager-v2/connections', 'ci-share-2'],
            'settings' => ['vpn_manager_v2_tab_settings', '/admin/plugins/vpn-manager-v2/settings', 'ci-settings'],
        ];
        $tabs = [];
        foreach ($items as $key => $item) {
            $tabs[] = [
                'key' => $key,
                'label' => self::t($item[0]),
                'href' => base_href($item[1]),
                'icon' => $item[2],
                'active' => $active === $key,
            ];
        }

        return $tabs;
    }

    public static function viewData(string $active, array $data = []): array
    {
        $assetPath = __DIR__ . '/assets/vpn-manager-v2.js';
        $assetUrl = base_href('/plugins/vpn-manager-v2/assets/vpn-manager-v2.js');
        $assetVersion = is_file($assetPath) ? (string)filemtime($assetPath) : (string)time();

        return array_merge([
            'tabs' => self::tabs($active),
            'styles' => [],
            'footer_scripts' => [$assetUrl . '?v=' . $assetVersion],
        ], $data);
    }

    public static function publicViewData(array $data = []): array
    {
        $assetPath = __DIR__ . '/assets/vpn-manager-v2.js';
        $assetUrl = base_href('/plugins/vpn-manager-v2/assets/vpn-manager-v2.js');
        $assetVersion = is_file($assetPath) ? (string)filemtime($assetPath) : (string)time();

        return array_merge([
            'styles' => [],
            'footer_scripts' => [$assetUrl . '?v=' . $assetVersion],
        ], $data);
    }
}
