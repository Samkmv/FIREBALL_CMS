<?php

use FBL\Plugins\PluginInterface;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\Commerce\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

final class FireballPluginCommerce implements PluginInterface
{
    public const SLUG = 'commerce';

    public function install(): void {}
    public function uninstall(): void {}
    public function activate(): void {}
    public function deactivate(): void {}

    public static function hasMigration(string $migration): bool
    {
        return (bool)db()->query('SELECT 1 FROM plugin_migrations WHERE plugin_slug = ? AND migration = ?',
            [self::SLUG, $migration])->getColumn();
    }

    public function boot(): void
    {
        add_filter('admin_menu', static function (array $menu): array {
            $menu[] = [
                'group' => 'applications',
                'label' => self::t('admin_title'),
                'href' => base_href('/admin/commerce'),
                'icon' => 'ci-shopping-bag',
                'plugin_menu' => true,
                'order' => 48,
            ];
            return $menu;
        });
        add_filter('admin_dashboard_widgets', static function (array $widgets): array {
            $stats = (new \Fireball\Commerce\Orders())->stats();
            $widgets[] = [
                'plugin' => self::SLUG,
                'title' => self::t('admin_title'),
                'icon' => 'ci-shopping-bag',
                'href' => base_href('/admin/commerce'),
                'metrics' => [
                    ['label' => self::t('new_orders'), 'value' => $stats['new_orders'], 'tone' => 'info'],
                    ['label' => self::t('low_stock'), 'value' => $stats['low_stock'], 'tone' => $stats['low_stock'] > 0 ? 'warning' : 'success'],
                    ['label' => self::t('products'), 'value' => $stats['products']],
                ],
                'content' => plugin_view(self::SLUG, 'admin/widget', [], false),
            ];
            return $widgets;
        });
        add_filter('site_homepage_modes', static function (array $modes): array {
            $modes['commerce'] = [
                'label' => self::t('storefront'),
                'render' => static fn(array $data): string => (new \Fireball\Commerce\Controllers\ShopController())->home($data),
            ];
            return $modes;
        });
        add_filter('public_navigation_search', static function (array $search): array {
            if (self::storefrontRequest()) {
                return [
                    'action' => base_href('/shop'),
                    'suggest_url' => base_href('/shop/search/suggest'),
                    'label' => self::t('product_search'),
                    'max_length' => 100,
                ];
            }
            return $search;
        });
        add_filter('public_navigation_cart', static function (?array $navigation): ?array {
            if (!self::storefrontRequest()) {
                return $navigation;
            }
            $cart = new \Fireball\Commerce\Cart();
            return [
                'count' => array_sum($cart->items()),
                'label' => self::t('cart'),
                'href' => base_href('/shop/cart'),
                'ordersLabel' => self::t('my_orders'),
                'ordersHref' => base_href('/profile/shop'),
                'favoritesLabel' => self::t('favorites'),
                'favoritesHref' => base_href('/shop/favorites'),
                'close' => self::t('close_cart'),
                'error' => self::t('cart_request_failed'),
                'script' => base_href('/plugins/commerce/mini-cart.js?v=' . filemtime(__DIR__ . '/assets/mini-cart.js')),
                'panel' => $cart->drawer(),
            ];
        });
        add_filter('profile_menu', static function (array $menu, array $user = []): array {
            if (!empty($user['id'])) {
                $menu[] = [
                    'key' => 'commerce-orders',
                    'label' => self::t('my_orders'),
                    'href' => base_href('/profile/shop'),
                    'icon' => 'ci-shopping-bag',
                    'order' => 55,
                    'plugin' => self::SLUG,
                ];
            }
            return $menu;
        }, 10, 2);
    }

    public static function t(string $key): string
    {
        return \FBL\Language::get('commerce_' . $key);
    }

    private static function storefrontRequest(): bool
    {
        $path = trim(uri_without_lang(), '/');
        return $path === 'shop' || str_starts_with($path, 'shop/') || $path === 'profile/shop'
            || ($path === '' && site_setting('homepage_type', 'default') === self::SLUG);
    }
}
