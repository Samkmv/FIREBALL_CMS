<?php

use Fireball\Subscriptions\Jobs\SubscriptionsMaintenanceJob;
use Fireball\Subscriptions\Repositories\ContentRuleRepository;
use Fireball\Subscriptions\Repositories\PlanRepository;
use Fireball\Subscriptions\Services\AccessService;
use Fireball\Subscriptions\Services\SettingsService;
use FBL\Plugins\PluginInterface;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\Subscriptions\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

final class FireballPluginSubscriptions implements PluginInterface
{
    public const SLUG = 'subscriptions';

    public function install(): void
    {
        (new SettingsService())->ensureDefaults();
        fireball_event('subscriptions.installed', ['slug' => self::SLUG]);
    }

    public function uninstall(): void
    {
        // Subscription, payment and audit records are retained intentionally.
    }

    public function activate(): void
    {
        (new SettingsService())->ensureDefaults();
        fireball_event('subscriptions.activated', ['slug' => self::SLUG]);
    }

    public function deactivate(): void
    {
        fireball_event('subscriptions.deactivated', ['slug' => self::SLUG]);
    }

    public function boot(): void
    {
        (new SettingsService())->ensureDefaults();

        add_filter('admin_menu', static function (array $menu): array {
            $menu[] = [
                'group' => 'applications',
                'label' => self::t('subscriptions_menu'),
                'href' => base_href('/admin/subscriptions'),
                'icon' => 'ci-credit-card',
                'plugin_menu' => true,
                'order' => 40,
            ];

            return $menu;
        });

        add_filter('profile_menu', static function (array $items): array {
            $items[] = [
                'key' => 'subscription',
                'label' => self::t('subscriptions_account_title'),
                'href' => base_href('/account/subscription'),
                'icon' => 'ci-credit-card',
                'order' => 35,
                'plugin' => self::SLUG,
            ];
            $items[] = [
                'key' => 'subscription-profile',
                'label' => self::t('subscriptions_profile_title'),
                'href' => base_href('/profile/subscription-details'),
                'icon' => 'ci-id-card',
                'order' => 36,
                'plugin' => self::SLUG,
            ];

            return $items;
        });

        add_action('admin_post_document_settings', [self::class, 'renderPostSettings']);
        add_action('admin_post_saved', [self::class, 'savePostSettings']);
        add_action('admin_post_deleting', static fn(int $postId) => (new ContentRuleRepository())->delete('post', $postId));
        add_filter('public_post_before_render', [self::class, 'filterPublicPost'], 20);
        add_filter('subscriptions_access_service', static fn(mixed $service): AccessService => $service instanceof AccessService ? $service : new AccessService());
        add_filter('fireball_scheduled_jobs', static function (array $jobs): array {
            $jobs['subscriptions_maintenance'] = [
                'class' => SubscriptionsMaintenanceJob::class,
                'schedule' => '*/10 * * * *',
                'plugin' => self::SLUG,
            ];

            return $jobs;
        });
    }

    public static function renderPostSettings(array $post, array $formData = []): void
    {
        $rule = (new ContentRuleRepository())->find('post', (int)($post['id'] ?? 0)) ?: [
            'access_mode' => 'public',
            'show_title' => 1,
            'show_excerpt' => 1,
            'show_image' => 1,
            'hide_video' => 1,
            'required_permission' => 'posts.view_paid',
            'plan_ids' => [],
        ];
        echo plugin_view(self::SLUG, 'admin/post-settings', [
            'rule' => $rule,
            'plans' => (new PlanRepository())->all(),
            'form_data' => $formData,
        ], false);
    }

    public static function savePostSettings(int $postId, array $data): void
    {
        if ($postId > 0) {
            (new ContentRuleRepository())->save('post', $postId, $data);
        }
    }

    public static function filterPublicPost(array $post, array $user = []): array
    {
        $decision = (new AccessService())->contentDecision((int)($user['id'] ?? 0), 'post', (int)($post['id'] ?? 0));
        if ($decision['allowed']) {
            $post['subscription_access'] = $decision;
            return $post;
        }

        $rule = (array)($decision['rule'] ?? []);
        if (empty($rule['show_title'])) {
            $post['title'] = self::t('subscriptions_protected_content');
        }
        if (empty($rule['show_excerpt'])) {
            $post['excerpt'] = '';
        }
        if (empty($rule['show_image'])) {
            $post['show_post_image'] = false;
            $post['seo_image'] = '';
        }
        $post['content'] = '<div class="alert alert-info subscriptions-access-message">'
            . '<h2 class="h5">' . htmlSC(self::t('subscriptions_access_post_title')) . '</h2>'
            . '<p>' . htmlSC(self::t('subscriptions_access_post_message')) . '</p>'
            . '<a class="btn btn-dark rounded-pill" href="' . htmlSC(base_href('/subscriptions/plans')) . '">'
            . htmlSC(self::t('subscriptions_view_plans')) . '</a></div>';
        $post['subscription_access'] = $decision;

        return $post;
    }

    public static function can(int $userId, string $permission, array $context = []): bool
    {
        return (new AccessService())->can($userId, $permission, $context);
    }

    public static function t(string $key): string
    {
        return return_translation($key);
    }

    public static function tabs(string $active): array
    {
        $definitions = [
            'overview' => ['subscriptions_admin_overview', '/admin/subscriptions', 'ci-layout'],
            'plans' => ['subscriptions_admin_plans', '/admin/subscriptions/plans', 'ci-package'],
            'subscribers' => ['subscriptions_admin_subscribers', '/admin/subscriptions/subscribers', 'ci-users'],
            'payments' => ['subscriptions_admin_payments', '/admin/subscriptions/payments', 'ci-credit-card'],
            'fields' => ['subscriptions_admin_profile_fields', '/admin/subscriptions/profile-fields', 'ci-list'],
            'settings' => ['subscriptions_admin_settings', '/admin/subscriptions/settings', 'ci-settings'],
        ];
        $tabs = [];
        foreach ($definitions as $key => [$label, $path, $icon]) {
            $tabs[] = [
                'key' => $key,
                'label' => self::t($label),
                'href' => base_href($path),
                'icon' => $icon,
                'active' => $key === $active,
            ];
        }

        return $tabs;
    }

    public static function viewData(array $data = []): array
    {
        $asset = __DIR__ . '/assets/subscriptions.css';

        return array_merge([
            'styles' => [base_href('/plugins/subscriptions/assets/subscriptions.css?v=' . (is_file($asset) ? filemtime($asset) : time()))],
        ], $data);
    }
}
