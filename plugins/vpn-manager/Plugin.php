<?php

use Fireball\VpnManager\Events\CommerceOrderPaidListener;
use Fireball\VpnManager\Repositories\VpnRepository;
use Fireball\VpnManager\Services\SettingsService;
use Fireball\VpnManager\Support\Schema;
use FBL\Plugins\PluginInterface;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

final class FireballPluginVpnManager implements PluginInterface
{
    public const SLUG = 'vpn-manager';

    public function install(): void
    {
        Schema::ensure();
        Schema::seedPermissions();
        SettingsService::ensureDefaults();
    }

    public function uninstall(): void
    {
        // Data removal is intentionally not automatic. VPN records contain billing and audit history.
    }

    public function activate(): void
    {
        Schema::ensure();
        Schema::seedPermissions();
        SettingsService::ensureDefaults();
        fireball_event('vpn_manager.activated', ['slug' => self::SLUG]);
    }

    public function deactivate(): void
    {
        fireball_event('vpn_manager.deactivated', ['slug' => self::SLUG]);
    }

    public function boot(): void
    {
        try {
            Schema::ensure();
            SettingsService::ensureDefaults();
        } catch (Throwable $exception) {
            log_error_details('VPN Manager schema check failed', [], $exception);
        }

        add_filter('admin_menu', function (array $menu): array {
            $menu[] = [
                'group' => 'applications',
                'label' => self::t('vpn_manager_menu'),
                'href' => base_href('/admin/plugins/vpn-manager'),
                'icon' => 'ci-server',
                'plugin_menu' => true,
                'order' => 80,
            ];

            return $menu;
        });

        fireball_listen('commerce.order.paid', [CommerceOrderPaidListener::class, 'handle']);
    }

    public static function t(string $key): string
    {
        return return_translation($key);
    }

    public static function tabs(string $active): array
    {
        $items = [
            'dashboard' => ['vpn_manager_tab_dashboard', '/admin/plugins/vpn-manager', 'ci-layout'],
            'servers' => ['vpn_manager_tab_servers', '/admin/plugins/vpn-manager/servers', 'ci-server'],
            'inbounds' => ['vpn_manager_tab_inbounds', '/admin/plugins/vpn-manager/inbounds', 'ci-link'],
            'plans' => ['vpn_manager_tab_plans', '/admin/plugins/vpn-manager/plans', 'ci-layers'],
            'subscriptions' => ['vpn_manager_tab_subscriptions', '/admin/plugins/vpn-manager/subscriptions', 'ci-credit-card'],
            'connections' => ['vpn_manager_tab_connections', '/admin/plugins/vpn-manager/connections', 'ci-link'],
            'users' => ['vpn_manager_tab_users', '/admin/plugins/vpn-manager/users', 'ci-user'],
            'statistics' => ['vpn_manager_tab_statistics', '/admin/plugins/vpn-manager/statistics', 'ci-activity'],
            'instructions' => ['vpn_manager_tab_instructions', '/admin/plugins/vpn-manager/instructions', 'ci-book-open'],
            'logs' => ['vpn_manager_tab_logs', '/admin/plugins/vpn-manager/logs', 'ci-list'],
            'settings' => ['vpn_manager_tab_settings', '/admin/plugins/vpn-manager/settings', 'ci-settings'],
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
        $settings = SettingsService::settings();

        return array_merge([
            'tabs' => self::tabs($active),
            'settings' => $settings,
            'serviceName' => $settings['service_name'] ?? 'My VPN',
        ], $data);
    }

    public static function repository(): VpnRepository
    {
        return new VpnRepository();
    }
}
