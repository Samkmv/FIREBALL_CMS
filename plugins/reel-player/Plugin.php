<?php
declare(strict_types=1);

use FBL\Plugins\PluginInterface;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\ReelPlayer\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
});

final class FireballPluginReelPlayer implements PluginInterface
{
    public function install(): void {}
    public function activate(): void {}
    public function deactivate(): void {}
    // Keep the private library when the plugin is removed or deactivated.
    public function uninstall(): void {}

    public function boot(): void
    {
        add_filter('admin_menu', static function (array $menu): array {
            if (check_creator()) {
                $menu[] = ['group' => 'applications', 'label' => 'Tape Room',
                    'href' => base_href('/admin/reel-player'), 'icon' => 'ci-music',
                    'plugin_menu' => true, 'order' => 36];
            }
            return $menu;
        });
    }
}
