<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../src/Support/Money.php';
require_once __DIR__ . '/../../src/Services/PaymentService.php';
require_once __DIR__ . '/../../src/Services/PublicOfferService.php';
require_once __DIR__ . '/../../src/Services/SettingsService.php';

final class FireballPluginSubscriptions
{
    public static string $locale = 'ru';
    public static function t(string $key): string
    {
        $translations = require __DIR__ . '/../../lang/' . self::$locale . '.php';
        return $translations[$key] ?? $key;
    }
}
FireballPluginSubscriptions::$locale = in_array($argv[2] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[2] : 'ru';
$previewTheme = ($argv[3] ?? '') === 'light' ? 'light' : 'dark';
function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path = ''): string { return $path; }
function base_url(string $path = ''): string { return 'https://example.test' . $path; }
function get_csrf_field(): string { return '<input type="hidden" name="csrf" value="test-only">'; }
function get_alerts(): void {}
function plugin_setting(string $slug, string $key, mixed $default = null): mixed { return $default; }
function return_translation(string $key): string {
    static $translations;
    $translations ??= array_replace(
        require __DIR__ . '/../../../../public/lang/' . FireballPluginSubscriptions::$locale . '.php',
        require __DIR__ . '/../../../../app/Languages/' . FireballPluginSubscriptions::$locale . '.php'
    );
    return $translations[$key] ?? $key;
}
function print_translation(string $key): string { return return_translation($key); }
function render_partial(string $name, array $data = []): string
{
    $renderer = new class {
        public function partial(string $name, array $data = []): string
        {
            $allowed = ['plugins/subscriptions/table', 'plugins/subscriptions/table_footer', 'plugins/subscriptions/responsive_table_cards'];
            if (!in_array($name, $allowed, true)) throw new RuntimeException('Unexpected public fixture partial');
            ob_start();
            (function () { extract(func_get_arg(1)); require __DIR__ . '/../../../../themes/default/partials/' . func_get_arg(0) . '.php'; })($name, $data);
            return (string)ob_get_clean();
        }
    };
    return $renderer->partial($name, $data);
}
function view(): object
{
    return new class {
        public function renderPartial(string $name, array $data = []): string
        {
            if ($name === 'admin/shell_open') return '<main class="fb-content"><header class="fb-page-header"><div class="fb-page-heading"><h1 class="fb-page-title">'
                . htmlSC($data['title'] ?? '') . '</h1><p class="fb-page-subtitle">' . htmlSC($data['subtitle'] ?? '')
                . '</p></div><div class="fb-page-actions">' . ($data['actions'] ?? '') . '</div></header><div class="fb-page-content subscriptions-admin">';
            if ($name === 'admin/shell_close') return '</div></main>';
            $allowed = ['admin/partials/table', 'admin/partials/responsive_table_cards', 'admin/partials/table_footer'];
            if (!in_array($name, $allowed, true)) throw new RuntimeException('Unexpected fixture partial');
            extract($data);
            ob_start();
            require __DIR__ . '/../../../../app/Views/themes/default/' . $name . '.php';
            return (string)ob_get_clean();
        }
    };
}
?>
<!doctype html><html lang="<?= htmlSC(FireballPluginSubscriptions::$locale) ?>" data-bs-theme="<?= $previewTheme ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Subscription preview</title><body>
<?php
$tabDefinitions = [
    'overview' => ['subscriptions_admin_overview', '/admin/subscriptions', 'ci-layout'],
    'plans' => ['subscriptions_admin_plans', '/admin/subscriptions/plans', 'ci-package'],
    'subscribers' => ['subscriptions_admin_subscribers', '/admin/subscriptions/subscribers', 'ci-user'],
    'exclusions' => ['subscriptions_admin_exclusions', '/admin/subscriptions/exclusions', 'ci-map-pin'],
    'payments' => ['subscriptions_admin_payments', '/admin/subscriptions/payments', 'ci-credit-card'],
    'content' => ['subscriptions_admin_content', '/admin/subscriptions/content', 'ci-file-text'],
    'fields' => ['subscriptions_admin_profile_fields', '/admin/subscriptions/profile-fields', 'ci-list'],
    'settings' => ['subscriptions_admin_settings', '/admin/subscriptions/settings', 'ci-settings'],
];
$activeTab = ['plan-form' => 'plans', 'field-form' => 'fields', 'exclusion-form' => 'exclusions'][$argv[1] ?? ''] ?? ($argv[1] ?? 'payments');
$tabs = [];
foreach ($tabDefinitions as $key => [$label, $href, $icon]) {
    $tabs[] = ['key' => $key, 'label' => FireballPluginSubscriptions::t($label), 'href' => $href, 'icon' => $icon, 'active' => $key === $activeTab];
}
$title = FireballPluginSubscriptions::t($tabDefinitions[$activeTab][0] ?? 'subscriptions_admin_title');
$fixturePlan = ['id' => 1, 'name' => 'Многоквартирные дома', 'slug' => 'residential', 'is_active' => 1, 'is_public' => 1,
    'auto_renew_enabled' => true, 'duration_value' => 30, 'duration_unit' => 'days', 'price_display' => '150 RUB', 'price_minor' => 15000];
$fixtureExclusion = ['id' => 1, 'address' => 'Октябрьская 15', 'normalized_address' => 'октябрьская 15', 'comment' => '', 'is_active' => 1,
    'created_at' => '2026-10-01 12:00:00', 'matched_users_count' => 14];
$fixtureField = ['id' => 1, 'label' => 'Телефон', 'field_key' => 'phone', 'field_type' => 'tel', 'is_system' => true, 'is_required' => true, 'is_active' => true];
if (in_array($argv[1] ?? '', ['account', 'account-recurring', 'account-renewal-off', 'account-utility', 'account-grace', 'account-cancelled', 'account-empty', 'account-long', 'account-no-permissions'], true)) {
    $scenario = $argv[1];
    $subscription = [
        'id' => 17, 'plan_id' => 9, 'plan_name' => $scenario === 'account-long' ? str_repeat('Подписка для многоквартирного дома ', 4) : 'Многоквартирные дома',
        'status' => $scenario === 'account-grace' ? 'grace_period' : ($scenario === 'account-cancelled' ? 'cancelled' : 'active'),
        'starts_at' => '2026-08-11 00:00:00', 'ends_at' => $scenario === 'account-utility' ? null : '2026-09-11 00:00:00',
        'grace_ends_at' => $scenario === 'account-grace' ? '2026-09-18 00:00:00' : null,
        'utility_managed' => $scenario === 'account-utility', 'auto_renew' => $scenario === 'account-recurring',
        'next_billing_at' => $scenario === 'account-recurring' ? '2026-09-11 00:00:00' : null,
        'cancelled_at' => $scenario === 'account-renewal-off' ? '2026-09-08 00:00:00' : null,
    ];
    if ($scenario === 'account-empty') $subscription = null;
    $permissions = $scenario === 'account-no-permissions' ? [] : [
        'posts.view_paid' => true, 'videos.view_paid' => true, 'camera_archive.view' => true,
        'camera_archive.download' => false,
    ];
    if ($scenario === 'account-long') $permissions['camera_archive.max_days'] = 30;
    $payments = [['id' => 1, 'invoice_id' => 120001, 'plan_name' => 'Многоквартирные дома', 'amount_minor' => 15000, 'currency' => 'RUB', 'status' => 'paid', 'created_at' => '2026-08-11 12:30:00']];
    require __DIR__ . '/../../views/public/account.php';
} elseif (($argv[1] ?? '') === 'checkout') {
    require_once __DIR__ . '/../../src/Repositories/ProfileRepository.php';
    $profile = array_fill_keys(Fireball\Subscriptions\Repositories\ProfileRepository::SYSTEM_FIELDS, '');
    $plan = ['id' => 1, 'name' => 'Тариф', 'description' => '', 'price_display' => '150 RUB', 'duration_value' => 30, 'duration_unit' => 'days', 'auto_renew_enabled' => true, 'permissions' => []];
    require __DIR__ . '/../../views/public/checkout.php';
} elseif (in_array($argv[1] ?? '', ['overview', 'plans', 'plan-form', 'exclusions', 'exclusion-form', 'fields', 'field-form', 'content'], true)) {
    $stats = ['active' => 124, 'expiring' => 8, 'paid_total_minor' => 1980000, 'failed' => 2];
    $by_plan = [['name' => 'Многоквартирные дома', 'total' => 118], ['name' => 'Для бизнеса', 'total' => 6]];
    $plans = [$fixturePlan]; $plan = $fixturePlan;
    $fields = [$fixtureField, array_replace($fixtureField, ['id' => 2, 'field_key' => 'note', 'label' => 'Комментарий', 'is_system' => false])];
    $field = $fixtureField; $field_types = ['text', 'tel', 'select'];
    $exclusions = [$fixtureExclusion]; $exclusion = $fixtureExclusion;
    $posts = [['id' => 1, 'title' => 'Новости дома', 'slug' => 'news', 'access_mode' => 'plans', 'plan_ids' => [1]]];
    $total = 1;
    if (($argv[4] ?? '') === 'empty') { $plans = $fields = $exclusions = $posts = $by_plan = []; $total = 0; }
    require __DIR__ . '/../../views/admin/' . ($argv[1] === 'overview' ? 'dashboard' : $argv[1]) . '.php';
} elseif (($argv[1] ?? '') === 'settings') {
    $settings = array_replace((new Fireball\Subscriptions\Services\SettingsService())->defaults(), [
        'merchant_login' => 'test-shop', 'password1_configured' => true, 'password2_configured' => true,
        'public_offer_page_id' => 1, 'public_offer_url' => 'https://example.test/offer.pdf',
    ]);
    $offer_pages = [['id' => 1, 'title' => 'Публичная оферта СКФ'], ['id' => 2, 'title' => 'Условия обслуживания']];
    require __DIR__ . '/../../views/admin/settings.php';
} elseif (($argv[1] ?? '') === 'subscribers') {
    $subscriptions = [];
    foreach (['disabled', 'disabled', 'active', 'expired', 'disabled', 'disabled'] as $index => $status) {
        $subscriptions[] = [
            'id' => $index + 1, 'user_id' => $index + 1, 'plan_id' => 1,
            'status' => $status, 'utility_managed' => $index === 5,
            'source' => [0 => 'external', 1 => 'manual', 5 => 'external'][$index] ?? 'robokassa',
            'user_name' => 'Тестовый подписчик ' . ($index + 1), 'user_email' => 'subscriber' . $index . '@example.test',
            'plan_name' => 'Тариф', 'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2027-01-01 00:00:00',
            // Second disabled row belongs to a user with another active subscription.
            'can_archive_subscriber' => in_array($index, [0, 3], true),
        ];
    }
    $plans = [['id' => 1, 'name' => 'Тариф']];
    $total = count($subscriptions);
    require __DIR__ . '/../../views/admin/subscribers.php';
} else {
    $payments = [];
    foreach (['pending', 'failed', 'pending', 'paid'] as $index => $status) {
        $payments[] = [
            'id' => $index + 1, 'invoice_id' => 120001 + $index, 'order_id' => $index + 1, 'user_id' => $index + 1,
            'provider' => 'robokassa', 'status' => $status, 'amount_minor' => 15000, 'currency' => 'RUB',
            'user_name' => 'Тестовый пользователь ' . ($index + 1), 'user_email' => 'test' . $index . '@example.test',
            'plan_name' => 'Многоквартирные дома', 'payment_type' => 'initial',
            'created_at' => '2026-09-01 12:30:00', 'paid_at' => $status === 'paid' ? '2026-09-01 12:35:00' : null,
            'signature_verified' => $status === 'paid',
            'error_message' => $index === 1 ? 'Payment timeout' : '',
            'webhook_status' => $index === 2 ? 'failed' : ($index === 3 ? 'processed' : ''),
            'webhook_created_at' => $index >= 2 ? '2026-09-01 12:35:00' : null,
            'webhook_signature_verified' => $index >= 2,
        ];
    }
    $total = count($payments);
    require __DIR__ . '/../../views/admin/payments.php';
}
?>
</body></html>
