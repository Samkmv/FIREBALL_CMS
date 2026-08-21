<?php

use Fireball\Subscriptions\Jobs\SubscriptionsMaintenanceJob;
use Fireball\Subscriptions\Repositories\ContentRuleRepository;
use Fireball\Subscriptions\Repositories\PlanRepository;
use Fireball\Subscriptions\Services\AccessService;
use Fireball\Subscriptions\Services\SettingsService;
use Fireball\Subscriptions\Support\ProtectedContent;
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

    private static ?AccessService $accessService = null;

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
        add_action('admin_user_deleting', [self::class, 'deleteUserData']);
        add_filter('public_post_before_render', [self::class, 'filterPublicPost'], 20);
        add_filter('public_posts_before_render', [self::class, 'filterPublicPosts'], 20);
        add_filter('public_page_before_render', [self::class, 'filterPublicPage'], 20);
        add_filter('public_video_access_allowed', [self::class, 'filterPublicVideoAccess'], 20);
        add_filter('fireball_editor_config', [self::class, 'filterEditorConfig'], 20);
        add_filter('fireball_editor_render_block', [self::class, 'filterEditorVideoBlock'], 20);
        add_filter('fireball_editor_style_assets', static function (array $assets): array {
            $asset = __DIR__ . '/assets/subscriptions.css';
            $assets[] = base_href('/plugins/subscriptions/assets/subscriptions.css?v=' . (is_file($asset) ? filemtime($asset) : time()));

            return array_values(array_unique($assets));
        });
        add_filter('fireball_editor_script_assets', static function (array $assets): array {
            $asset = __DIR__ . '/assets/editor.js';
            $assets[] = base_href('/plugins/subscriptions/assets/editor.js?v=' . (is_file($asset) ? filemtime($asset) : time()));

            return array_values(array_unique($assets));
        });
        add_filter('subscriptions_access_service', static fn(mixed $service): AccessService => $service instanceof AccessService ? $service : new AccessService());
        add_filter('admin_dashboard_widgets', [self::class, 'dashboardWidgets'], 10);
        add_filter('fireball_scheduled_jobs', static function (array $jobs): array {
            $jobs['subscriptions_maintenance'] = [
                'class' => SubscriptionsMaintenanceJob::class,
                'schedule' => '*/10 * * * *',
                'plugin' => self::SLUG,
            ];

            return $jobs;
        });
    }

    public static function dashboardWidgets(array $widgets, array $context = []): array
    {
        try {
            $active = (int)db()->query(
                "SELECT COUNT(*) FROM subscriptions
                 WHERE status IN ('active', 'grace_period', 'cancelled')
                   AND starts_at <= NOW() AND COALESCE(grace_ends_at, ends_at) > NOW()"
            )->getColumn();
            $expiring = (int)db()->query(
                "SELECT COUNT(*) FROM subscriptions
                 WHERE status IN ('active', 'cancelled')
                   AND ends_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)"
            )->getColumn();
            $failed = (int)db()->query(
                "SELECT COUNT(*) FROM subscription_payments WHERE status = 'failed'"
            )->getColumn();

            $widgets[] = [
                'plugin' => self::SLUG,
                'title' => self::t('subscriptions_admin_title'),
                'subtitle' => self::t('subscriptions_admin_subtitle'),
                'icon' => 'ci-award',
                'href' => base_href('/admin/subscriptions'),
                'metrics' => [
                    ['label' => self::t('subscriptions_stat_active'), 'value' => $active, 'tone' => 'success'],
                    ['label' => self::t('subscriptions_stat_expiring'), 'value' => $expiring, 'tone' => $expiring > 0 ? 'warning' : 'neutral'],
                    ['label' => self::t('subscriptions_stat_failed'), 'value' => $failed, 'tone' => $failed > 0 ? 'danger' : 'neutral'],
                ],
            ];
        } catch (\Throwable $exception) {
            log_error_details('Subscriptions dashboard widget failed', [], $exception);
        }

        return $widgets;
    }

    public static function renderPostSettings(array $post, array $formData = []): void
    {
        $rule = (new ContentRuleRepository())->find('post', (int)($post['id'] ?? 0)) ?: [
            'access_mode' => 'public',
            'show_title' => 1,
            'show_excerpt' => 1,
            'show_image' => 1,
            'hide_video' => 0,
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
            $repository = new ContentRuleRepository();
            $repository->save('post', $postId, $data);
            self::saveVideoRules($repository, (string)($data['content'] ?? ''));
        }
    }

    public static function filterEditorConfig(array $config): array
    {
        $config['subscriptionVideoAccess'] = [
            'plans' => array_map(static fn(array $plan): array => [
                'id' => (int)$plan['id'],
                'name' => (string)$plan['name'],
            ], (new PlanRepository())->all()),
            'labels' => [
                'title' => self::t('subscriptions_video_access_title'),
                'public' => self::t('subscriptions_access_mode_public'),
                'subscribers' => self::t('subscriptions_access_mode_subscribers'),
                'plans' => self::t('subscriptions_access_mode_plans'),
                'allowedPlans' => self::t('subscriptions_allowed_plans'),
            ],
        ];

        return $config;
    }

    public static function filterEditorVideoBlock(mixed $html, array $block): mixed
    {
        if ((string)($block['type'] ?? '') !== 'video') {
            return $html;
        }
        $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
        if (array_key_exists('subscriptionAccessMode', $blockData)) {
            $user = get_user();
            if (self::canViewEmbeddedVideo($blockData, is_array($user) ? $user : [], self::accessService())) {
                return $html;
            }

            return self::videoAccessMessage();
        }
        $blockId = trim((string)($block['id'] ?? ''));
        if ($blockId === '') {
            return $html;
        }
        $decision = self::accessService()->contentDecision((int)(get_user()['id'] ?? 0), 'video', $blockId);
        if (!empty($decision['allowed'])) {
            return $html;
        }

        return self::videoAccessMessage();
    }

    private static function canViewEmbeddedVideo(array $blockData, array $user, AccessService $access): bool
    {
        $mode = (string)($blockData['subscriptionAccessMode'] ?? 'public');
        if (!in_array($mode, ['public', 'authenticated', 'subscribers', 'plans'], true) || $mode === 'public') {
            return true;
        }

        $userId = (int)($user['id'] ?? 0);
        if (in_array((string)($user['role'] ?? ''), ['creator', 'admin'], true)) {
            return true;
        }
        if ($userId <= 0) {
            return false;
        }
        if ($mode === 'authenticated') {
            return true;
        }
        if ($mode === 'subscribers') {
            return $access->can($userId, 'videos.view_paid');
        }

        $allowedPlans = array_values(array_unique(array_filter(array_map('intval', (array)($blockData['subscriptionPlanIds'] ?? [])))));
        $subscription = $access->activeSubscription($userId);

        return $subscription !== null && in_array((int)$subscription['plan_id'], $allowedPlans, true);
    }

    /**
     * The current editor stores public HTML followed by a base64 state snapshot.
     * Access checks must therefore run after the shared post cache, not only while
     * the legacy JSON document is rendered by BlockRenderer.
     */
    private static function filterEmbeddedVideosInContent(string $content, array $user, AccessService $access): string
    {
        $videoBlocks = array_values(array_filter(
            self::editorBlocksFromContent($content),
            static fn(mixed $block): bool => is_array($block) && (string)($block['type'] ?? '') === 'video'
        ));
        if ($videoBlocks === []) {
            return $content;
        }

        $decisions = [];
        $denied = false;
        foreach ($videoBlocks as $index => $block) {
            $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
            $blockId = trim((string)($block['id'] ?? ''));
            if (array_key_exists('subscriptionAccessMode', $blockData)) {
                $allowed = self::canViewEmbeddedVideo($blockData, $user, $access);
            } elseif ($blockId !== '') {
                $allowed = !empty($access->contentDecision((int)($user['id'] ?? 0), 'video', $blockId)['allowed']);
            } else {
                $allowed = true;
            }

            $decisions[$index] = $allowed;
            if ($blockId !== '') {
                $decisions[$blockId] = $allowed;
            }
            $denied = $denied || !$allowed;
        }

        if (!$denied) {
            return $content;
        }

        // DOM is available on supported production installations. Fail closed on
        // very limited PHP builds so a protected source is never returned intact.
        if (!class_exists(\DOMDocument::class)) {
            return self::stripEditorSnapshot(ProtectedContent::replaceVideos($content, self::videoAccessMessage()));
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="subscriptions-public-content-root">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        if (!$loaded) {
            return self::stripEditorSnapshot(ProtectedContent::replaceVideos($content, self::videoAccessMessage()));
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[@data-fb-block="video"]');
        if ($nodes !== false) {
            foreach (array_values(iterator_to_array($nodes)) as $index => $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $blockId = trim($node->getAttribute('data-fb-block-id'));
                $allowed = $blockId !== '' && array_key_exists($blockId, $decisions)
                    ? $decisions[$blockId]
                    : ($decisions[$index] ?? true);
                if (!$allowed) {
                    self::replaceDomNodeWithHtml($document, $node, self::videoAccessMessage());
                }
            }
        }

        // The snapshot contains the original media URL. It is editor metadata and
        // must not be exposed in the public response when any video is protected.
        $snapshots = $xpath->query('//template[@data-fb-editor-state]');
        if ($snapshots !== false) {
            foreach (iterator_to_array($snapshots) as $snapshot) {
                $snapshot->parentNode?->removeChild($snapshot);
            }
        }

        $root = $document->getElementById('subscriptions-public-content-root');
        if (!$root) {
            return '';
        }
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function editorBlocksFromContent(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        if ($content[0] === '{') {
            $document = json_decode($content, true);

            return is_array($document) && is_array($document['blocks'] ?? null)
                ? array_values($document['blocks'])
                : [];
        }

        if (!preg_match('~<template\b[^>]*data-fb-editor-state[^>]*>([^<]+)</template>~is', $content, $matches)) {
            return [];
        }
        $encoded = html_entity_decode(trim((string)$matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $json = base64_decode($encoded, true);
        if ($json === false) {
            return [];
        }
        $document = json_decode($json, true);

        return is_array($document) && is_array($document['blocks'] ?? null)
            ? array_values($document['blocks'])
            : [];
    }

    private static function replaceDomNodeWithHtml(\DOMDocument $document, \DOMNode $node, string $html): void
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        $fragmentDocument = new \DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $fragmentDocument->loadHTML(
            '<?xml encoding="UTF-8"><div id="subscriptions-replacement-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $replacementRoot = $loaded ? $fragmentDocument->getElementById('subscriptions-replacement-root') : null;
        if (!$replacementRoot) {
            $parent->removeChild($node);
            return;
        }

        foreach (iterator_to_array($replacementRoot->childNodes) as $replacement) {
            $parent->insertBefore($document->importNode($replacement, true), $node);
        }
        $parent->removeChild($node);
    }

    private static function stripEditorSnapshot(string $content): string
    {
        return trim((string)preg_replace(
            '~<template\b[^>]*data-fb-editor-state[^>]*>.*?</template>~is',
            '',
            $content
        ));
    }

    private static function saveVideoRules(ContentRuleRepository $repository, string $content): void
    {
        foreach (self::editorBlocksFromContent($content) as $block) {
            if (!is_array($block) || (string)($block['type'] ?? '') !== 'video' || trim((string)($block['id'] ?? '')) === '') {
                continue;
            }
            $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
            $repository->save('video', (string)$block['id'], [
                'subscription_access_mode' => (string)($blockData['subscriptionAccessMode'] ?? 'public'),
                'subscription_plan_ids' => (array)($blockData['subscriptionPlanIds'] ?? []),
                'subscription_show_title' => '1',
                'subscription_show_excerpt' => '1',
                'subscription_show_image' => '1',
                'subscription_hide_video' => '',
                'subscription_required_permission' => 'videos.view_paid',
            ]);
        }
    }

    public static function deleteUserData(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        db()->query(
            'UPDATE subscription_payments SET parent_payment_id = NULL WHERE parent_payment_id IN (SELECT id FROM (SELECT id FROM subscription_payments WHERE user_id = ?) AS user_payments)',
            [$userId]
        );
        db()->query('UPDATE subscriptions SET parent_payment_id = NULL WHERE user_id = ?', [$userId]);
        db()->query('DELETE FROM subscription_payments WHERE user_id = ?', [$userId]);
        db()->query('DELETE FROM subscription_orders WHERE user_id = ?', [$userId]);
        db()->query('DELETE FROM subscriptions WHERE user_id = ?', [$userId]);
        db()->query('DELETE FROM subscription_profiles WHERE user_id = ?', [$userId]);
    }

    public static function filterPublicPost(array $post, array $user = []): array
    {
        return self::applyPostAccess($post, $user, self::accessService());
    }

    public static function filterPublicPosts(array $posts, array $user = []): array
    {
        $access = self::accessService();
        $filtered = [];
        foreach ($posts as $post) {
            if (is_array($post)) {
                $filtered[] = self::applyPostAccess($post, $user, $access);
            }
        }

        return $filtered;
    }

    public static function filterPublicPage(array $page, array $user = []): array
    {
        return $page;
    }

    public static function filterPublicVideoAccess(bool $allowed, array $user = []): bool
    {
        return $allowed;
    }

    /**
     * Доступ к записи и доступ к её видеоблокам независимы:
     * закрытая запись заменяется целиком, а открытая сохраняет уже обработанные блоки.
     */
    private static function applyPostAccess(array $post, array $user, AccessService $access): array
    {
        $decision = $access->contentDecision((int)($user['id'] ?? 0), 'post', (int)($post['id'] ?? 0));
        if ($decision['allowed']) {
            if (is_string($post['content'] ?? null) && $post['content'] !== '') {
                $post['content'] = self::filterEmbeddedVideosInContent($post['content'], $user, $access);
            }
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
        $post['content'] = self::postAccessMessage($decision, $user);
        $post['seo_description'] = !empty($rule['show_excerpt']) && trim((string)($post['excerpt'] ?? '')) !== ''
            ? trim((string)$post['excerpt'])
            : self::t('subscriptions_access_post_message');
        $post['subscription_access'] = $decision;

        return $post;
    }

    private static function videoAccessMessage(): string
    {
        return '<section class="subscriptions-access-message subscriptions-access-message--compact" role="status">'
            . '<span class="subscriptions-access-message__icon" aria-hidden="true"><i class="ci-play"></i></span>'
            . '<div class="subscriptions-access-message__content">'
            . '<span class="subscriptions-access-message__eyebrow">' . htmlSC(self::t('subscriptions_access_eyebrow')) . '</span>'
            . '<h3>' . htmlSC(self::t('subscriptions_access_video_title')) . '</h3>'
            . '<p>' . htmlSC(self::t('subscriptions_access_video_message')) . '</p>'
            . '<div class="subscriptions-access-message__actions"><a class="btn btn-dark rounded-pill" href="' . htmlSC(base_href('/subscriptions/plans')) . '">'
            . htmlSC(self::t('subscriptions_view_plans')) . '</a></div></div></section>';
    }

    private static function postAccessMessage(array $decision, array $user): string
    {
        $reason = (string)($decision['reason'] ?? 'subscription_required');
        $titleKey = 'subscriptions_access_post_title';
        $messageKey = 'subscriptions_access_post_message';
        $icon = 'ci-lock';

        if ($reason === 'authentication_required') {
            $titleKey = 'subscriptions_access_login_title';
            $messageKey = 'subscriptions_access_login_message';
            $icon = 'ci-user';
        } elseif ($reason === 'plan_required') {
            $titleKey = 'subscriptions_access_plan_title';
            $messageKey = 'subscriptions_access_plan_message';
            $icon = 'ci-package';
        } elseif ($reason === 'permission_required') {
            $titleKey = 'subscriptions_access_permission_title';
            $messageKey = 'subscriptions_access_permission_message';
            $icon = 'ci-shield';
        }

        $actions = '<a class="btn btn-dark rounded-pill" href="' . htmlSC(base_href('/subscriptions/plans')) . '">'
            . htmlSC(self::t('subscriptions_view_plans')) . '</a>';
        if (empty($user['id'])) {
            $actions .= '<a class="btn btn-outline-secondary rounded-pill" href="' . htmlSC(base_href('/login')) . '">'
                . htmlSC(self::t('subscriptions_login')) . '</a>';
        }

        return '<section class="subscriptions-access-message" role="status">'
            . '<span class="subscriptions-access-message__icon" aria-hidden="true"><i class="' . htmlSC($icon) . '"></i></span>'
            . '<div class="subscriptions-access-message__content">'
            . '<span class="subscriptions-access-message__eyebrow">' . htmlSC(self::t('subscriptions_access_eyebrow')) . '</span>'
            . '<h2>' . htmlSC(self::t($titleKey)) . '</h2>'
            . '<p>' . htmlSC(self::t($messageKey)) . '</p>'
            . '<div class="subscriptions-access-message__actions">' . $actions . '</div>'
            . '</div></section>';
    }

    private static function accessService(): AccessService
    {
        return self::$accessService ??= new AccessService();
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
            'content' => ['subscriptions_admin_content', '/admin/subscriptions/content', 'ci-file-text'],
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
