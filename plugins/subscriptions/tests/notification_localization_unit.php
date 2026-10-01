<?php

declare(strict_types=1);

// Isolated language/feed/maintenance checks: no working DB, provider or push calls.
define('LANGS', array_combine(['ru', 'en', 'de', 'zh-cn'], array_map(
    static fn(string $code): array => ['code' => $code], ['ru', 'en', 'de', 'zh-cn']
)));
define('DEFAULT_LOCALE', 'ru');
define('DEBUG', false);

require_once __DIR__ . '/../../../core/Localization.php';
require_once __DIR__ . '/../../../core/Language.php';
require_once __DIR__ . '/../../../core/Plugins/HookManager.php';
require_once __DIR__ . '/../../../core/Plugins/PluginInterface.php';
require_once __DIR__ . '/../../../app/Services/NotificationService.php';
require_once __DIR__ . '/../Plugin.php';

$testApp = new class {
    public mixed $db = null;
    public string $locale = 'ru';
    public function get(string $key, mixed $default = null): mixed
    {
        return $key === 'lang' ? ['code' => $this->locale] : $default;
    }
};
$hooks = new \FBL\Plugins\HookManager();
$hooks->addFilter('notification_feed_item', [\Fireball\Subscriptions\Support\NotificationPresentation::class, 'localize']);
$notifications = [];
$errors = [];
$checks = 0;

function app(): object { global $testApp; return $testApp; }
function return_translation(string $key): string { return \FBL\Language::get($key); }
function base_href(string $path = ''): string { return 'https://example.test' . $path; }
function apply_filters_safe(string $hook, mixed $value, mixed ...$args): mixed
{
    global $hooks;
    return $hooks->applyFiltersSafely($hook, $value, ...$args);
}
function notification_create(array $payload): array
{
    global $notifications;
    $notifications[] = $payload;
    return [];
}
function log_error_details(string $message, array $context, Throwable $exception): void
{
    global $errors;
    $errors[] = $message . ': ' . $exception->getMessage();
}
function checkNotification(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}

$testDb = new class {
    public array $events = [];
    public function query(string $sql, array $params = []): object
    {
        $rows = [];
        $one = null;
        if (str_contains($sql, 'DATEDIFF(s.ends_at, NOW()) AS days_left')) {
            foreach ([1, 3] as $days) {
                $rows[] = ['id' => $days, 'user_id' => 7, 'ends_at' => '2026-10-04 10:00:00', 'days_left' => $days];
            }
        } elseif (str_starts_with($sql, 'SELECT id FROM subscription_events')) {
            $one = isset($this->events[$params[0] . ':' . $params[1]]) ? ['id' => 1] : null;
        } elseif (str_starts_with($sql, 'INSERT INTO subscription_events')) {
            $this->events[$params[0] . ':' . $params[4]] = true;
        } else {
            throw new RuntimeException('Unexpected DB operation: ' . $sql);
        }
        return new class($rows, $one) {
            public function __construct(private array $rows, private mixed $one) {}
            public function get(): array { return $this->rows; }
            public function getOne(): mixed { return $this->one; }
        };
    }
};
function db(): object { global $testDb; return $testDb; }

$feed = new class extends \App\Services\NotificationService {
    public function present(array $row): array { return $this->feedItem($row); }
};
$baseRow = [
    'id' => 42, 'user_id' => 7, 'type' => 'subscription', 'source' => 'subscriptions',
    'title' => 'subscriptions_notification_expiring_title',
    'message' => 'subscriptions_notification_expiring_message',
    'action_url' => '/account/subscription', 'created_at' => '2026-09-27 17:00:02',
    'metadata' => '{}',
];

foreach (array_keys(LANGS) as $locale) {
    $testApp->locale = $locale;
    \FBL\Language::$lang_data = [];
    checkNotification(return_translation($baseRow['title']) === $baseRow['title'], "$locale: reproduce untranslated CLI context");
    // Same registration as cron.php, exercising real CMS locale fallback/loading.
    \FBL\Language::registerPluginLanguage('subscriptions', __DIR__ . '/../lang');
    $translations = require __DIR__ . '/../lang/' . $locale . '.php';
    $legacy = $feed->present($baseRow);
    checkNotification($legacy['title'] === $translations[$baseRow['title']], "$locale: legacy title translated");
    checkNotification($legacy['text'] === $translations['subscriptions_notification_expiring_message_generic'], "$locale: no invented legacy day count");
    checkNotification($legacy['source_label'] === $translations['subscriptions_menu'], "$locale: source label translated");
    checkNotification($legacy['notification_id'] === 42 && $legacy['created_at'] === $baseRow['created_at'] && $legacy['url'] === $baseRow['action_url'], "$locale: identity/date/action preserved");

    foreach (['activated', 'expiring', 'recurring_failed'] as $event) {
        foreach ([1, 3] as $days) {
            $prefix = 'subscriptions_notification_' . $event;
            $row = array_replace($baseRow, [
                'title' => 'Previously saved title', 'message' => 'Previously saved body',
                'metadata' => json_encode(['subscription_notification' => $event, 'days' => $days]),
            ]);
            $item = $feed->present($row);
            checkNotification($item['title'] === $translations[$prefix . '_title'], "$locale/$event: title follows UI language");
            checkNotification($item['text'] === str_replace(':days', (string)$days, $translations[$prefix . '_message']), "$locale/$event: message and days translated");
        }
        $legacyEvent = $feed->present(array_replace($baseRow, ['title' => 'subscriptions_notification_' . $event . '_title', 'message' => 'subscriptions_notification_' . $event . '_message']));
        checkNotification(!str_contains($legacyEvent['title'] . $legacyEvent['text'], 'subscriptions_'), "$locale/$event: all legacy raw keys repaired");
    }
    $malformed = $feed->present(array_replace($baseRow, ['metadata' => '{invalid']));
    checkNotification($malformed['text'] === $legacy['text'], "$locale: malformed old metadata handled");
    $knownBody = $feed->present(array_replace($baseRow, ['message' => 'Your subscription expires in 3 day(s).']));
    checkNotification($knownBody['text'] === 'Your subscription expires in 3 day(s).', "$locale: existing historic day count retained");
    $custom = $feed->present(array_replace($baseRow, ['title' => 'Custom notice', 'message' => 'Custom text']));
    checkNotification($custom['title'] === 'Custom notice' && $custom['text'] === 'Custom text', "$locale: custom subscription notice untouched");
    $other = $feed->present(array_replace($baseRow, ['source' => 'calendar']));
    checkNotification($other['source_label'] === 'Calendar' && $other['title'] === $baseRow['title'], "$locale: other plugins untouched");

    $notifications = [];
    $testDb->events = [];
    $maintenance = new \Fireball\Subscriptions\Services\MaintenanceService();
    $sendExpiry = new ReflectionMethod($maintenance, 'sendExpiryNotifications');
    checkNotification($sendExpiry->invoke($maintenance) === 2, "$locale: expiry sender emits notices");
    foreach ($notifications as $payload) {
        $days = $payload['metadata']['days'];
        checkNotification($payload['title'] === $translations['subscriptions_notification_expiring_title'], "$locale: stored/push title translated");
        checkNotification($payload['message'] === str_replace(':days', (string)$days, $translations['subscriptions_notification_expiring_message']), "$locale: stored/push message translated");
        checkNotification($payload['metadata']['subscription_notification'] === 'expiring' && $payload['metadata']['subscription_id'] === $days, "$locale: event/days stored for presentation");
    }
    checkNotification($sendExpiry->invoke($maintenance) === 0 && count($notifications) === 2, "$locale: notice deduplication retained");
}

checkNotification($errors === [], 'No translation or maintenance errors');
$hooks->addFilter('notification_feed_item', static function (): never { throw new RuntimeException('Broken third-party filter'); });
checkNotification($feed->present($baseRow)['notification_id'] === 42, 'Failing plugin filter cannot break the feed');
checkNotification(count($errors) === 1, 'Failing filter is logged');
echo "Subscription notification localization tests passed: {$checks} checks." . PHP_EOL;
