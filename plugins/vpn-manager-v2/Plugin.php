<?php

use FBL\Plugins\PluginInterface;
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
        fireball_event('vpn_manager_v2.activated', ['slug' => self::SLUG]);
    }

    public function deactivate(): void
    {
        fireball_event('vpn_manager_v2.deactivated', ['slug' => self::SLUG]);
    }

    public function boot(): void
    {
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
    }

    public static function t(string $key): string
    {
        return return_translation($key);
    }

    public static function permissions(): array
    {
        return Permissions::definitions();
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
}
