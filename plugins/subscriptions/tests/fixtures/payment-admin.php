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
function return_translation(string $key): string { return ['admin_table_showing' => 'Показано', 'admin_table_of' => 'из'][$key] ?? $key; }
function print_translation(string $key): string { return return_translation($key); }
function view(): object
{
    return new class {
        public function renderPartial(string $name, array $data = []): string
        {
            if ($name === 'admin/shell_open') return '<main class="container-fluid p-4 subscriptions-admin">';
            if ($name === 'admin/shell_close') return '</main>';
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
