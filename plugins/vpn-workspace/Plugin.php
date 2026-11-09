<?php

use FBL\Plugins\PluginInterface;

final class FireballPluginVpnWorkspace implements PluginInterface
{
    public function install(): void {}
    public function uninstall(): void {}
    public function activate(): void {}
    public function deactivate(): void {}

    public function boot(): void
    {
        add_filter('admin_menu', static function (array $menu): array {
            $menu[] = [
                'group' => 'applications',
                'label' => return_translation('vpn_workspace_title'),
                'href' => base_href('/admin/plugins/vpn-workspace'),
                'icon' => 'ci-server',
                'plugin_menu' => true,
                'order' => 82,
            ];

            return $menu;
        });
    }
}
