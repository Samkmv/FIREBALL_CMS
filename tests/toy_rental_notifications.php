<?php
declare(strict_types=1);

namespace App\Services {
    // Capture the native service boundary: never send a real push during this test.
    final class NotificationService
    {
        public static array $dispatches = [];
        public static function createForUsers(array $userIds, array $payload): array
        {
            self::$dispatches[] = ['users' => $userIds, 'payload' => $payload];
            return [];
        }
    }
}

namespace {
    define('ROOT', dirname(__DIR__));
    require ROOT . '/config/config.php';
    require ROOT . '/vendor/autoload.php';
    require ROOT . '/helpers/helpers.php';
    require ROOT . '/plugins/toy-car-rental/Plugin.php';

    final class RentalNotificationDatabase extends \FBL\Database
    {
        private array $rows = [];
        public array $roles = [7 => ['id' => 7, 'name' => 'Rental operator', 'slug' => 'rental-operator']];
        public array $users = [1 => 'creator', 2 => 'admin', 3 => 'moderator', 4 => 'user', 5 => 'rental-operator'];
        public array $locales = [];
        public function __construct() {}
        public function query(string $query, array $params = []): static
        {
            $this->rows = [];
            if (str_contains($query, 'FROM user_roles WHERE id = ?')) {
                $role = $this->roles[(int)$params[0]] ?? null;
                if ($role && !in_array($role['slug'], ['admin', 'creator'], true)) $this->rows[] = $role;
            } elseif (str_starts_with($query, 'SELECT id, locale FROM users WHERE ')) {
                $conditions = [];
                if ($params) $conditions[] = 'role = ?';
                $creatorEnabled = str_contains($query, "role = 'creator'");
                if ($creatorEnabled) $conditions[] = "role = 'creator'";
                if ($query !== 'SELECT id, locale FROM users WHERE ' . implode(' OR ', $conditions) . ' ORDER BY id ASC') {
                    throw new \RuntimeException('Recipient selection must not include administrators or all users.');
                }
                foreach ($this->users as $id => $role) {
                    if (($creatorEnabled && $role === 'creator') || ($params && $role === $params[0])) $this->rows[] = ['id' => $id, 'locale' => $this->locales[$id] ?? ''];
                }
            } else {
                throw new \RuntimeException('Unexpected notification fixture query: ' . $query);
            }
            return $this;
        }
        public function get(): false|array { return $this->rows; }
        public function getOne() { return $this->rows[0] ?? false; }
    }
    $app = (new \ReflectionClass(\FBL\Application::class))->newInstanceWithoutConstructor();
    \FBL\Application::$app = $app;
    $app->db = $database = new RentalNotificationDatabase();
    $app->session = (new \ReflectionClass(\FBL\Session::class))->newInstanceWithoutConstructor();
    $_SESSION = [];
    $app->plugins = new \FBL\Plugins\PluginManager();
    $settingsCache = new \ReflectionProperty(\FBL\Plugins\PluginManager::class, 'settingsCache');
    (new \ReflectionProperty(\App\Models\SiteSetting::class, 'cache'))->setValue(null, []);
    $notify = new \ReflectionMethod(\FireballPluginToyCarRental::class, 'notifyOperatorsAboutOverdueRide');
    $checks = 0;
    $verify = static function (bool $condition, string $message) use (&$checks): void {
        if (!$condition) throw new \RuntimeException($message);
        $checks++;
    };
    foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
        $app->set('lang', LANGS[$locale]);
        \FBL\Language::$lang_data = require ROOT . '/plugins/toy-car-rental/lang/' . $locale . '.php';
        $database->locales = [1 => $locale, 5 => $locale];
        $legacy = \FireballPluginToyCarRental::localizeNotificationSource(['url' => '/admin/toy-rental'], ['source' => 'toy-car-rental']);
        $verify($legacy['url'] === base_href('/profile/toy-rental'), 'Previously saved rental alerts must open the new operator panel.');
        $verify($legacy['source_label'] === \FireballPluginToyCarRental::t('toy_rental_notification_source'), 'Preserve localized sources for old alerts.');
        $unrelated = ['url' => '/other', 'source_label' => 'Other'];
        $verify(\FireballPluginToyCarRental::localizeNotificationSource($unrelated, ['source' => 'other']) === $unrelated, 'Do not alter other notification sources.');
        foreach ([true, false] as $pushEnabled) {
            $settingsCache->setValue($app->plugins, ['toy-car-rental' => [
                'operator_role_id' => ['setting_value' => '7'],
                'overdue_push_enabled' => ['setting_value' => json_encode($pushEnabled)],
            ]]);
            foreach ([false, true] as $limitReached) {
                $notify->invoke(null, ['id' => 17, 'car_id' => 8, 'car_name' => 'Test car', 'car_number' => '2'], $limitReached);
                $dispatch = end(\App\Services\NotificationService::$dispatches);
                $payload = $dispatch['payload'];
                $verify($dispatch['users'] === [1, 5], 'Send rental notifications only to the selected role and the creator test copy.');
                $verify($payload['push_notification'] === $pushEnabled, 'Respect the rental push setting.');
                $verify($payload['store_unread'] === true, 'Keep website notifications when push is disabled.');
                $verify($payload['priority'] === 'high' && $payload['source'] === 'toy-car-rental', 'Use the native rental notification payload.');
                $verify($payload['action_url'] === '/profile/toy-rental', 'Notification clicks must open the protected operator panel.');
                $verify($payload['metadata'] === ['ride_id' => 17, 'car_id' => 8, 'event' => $limitReached ? 'limit_reached' : 'time_up', 'car_label' => 'Test car №2'], 'Retain ride identity and localized alert context.');
                $verify($payload['title'] === \FireballPluginToyCarRental::t($limitReached ? 'toy_rental_notification_limit_title' : 'toy_rental_notification_overdue_title'), 'Localize both expiry and limit alerts.');
                $verify($payload['message'] === \FireballPluginToyCarRental::t($limitReached ? 'toy_rental_notification_limit_text' : 'toy_rental_time_up_message', ['car' => 'Test car №2']), 'Translate the message without leaving placeholders.');
                $feed = \FireballPluginToyCarRental::localizeNotificationSource(['title' => 'Old language', 'text' => 'Old language'], [
                    'source' => 'toy-car-rental', 'metadata' => json_encode($payload['metadata']),
                ]);
                $verify($feed['title'] === $payload['title'] && $feed['text'] === $payload['message'], 'Website alerts must use the current CMS language.');
            }
        }
    }
    $verify(count(\App\Services\NotificationService::$dispatches) === 16, 'Every expiry case must reach the native service.');
    foreach ([0, 999] as $roleId) {
        $settingsCache->setValue($app->plugins, ['toy-car-rental' => ['operator_role_id' => ['setting_value' => json_encode($roleId)]]]);
        $notify->invoke(null, ['id' => 17, 'car_id' => 8]);
        $verify(end(\App\Services\NotificationService::$dispatches)['users'] === [1], 'Missing or deleted roles must notify only the creator test copy.');
    }
    $settingsCache->setValue($app->plugins, ['toy-car-rental' => [
        'operator_role_id' => ['setting_value' => '7'],
        'creator_notifications_enabled' => ['setting_value' => 'false'],
    ]]);
    $notify->invoke(null, ['id' => 17, 'car_id' => 8]);
    $verify(end(\App\Services\NotificationService::$dispatches)['users'] === [5], 'Disabling creator copies must leave only operators.');
    foreach ([0, 999] as $roleId) {
        $settingsCache->setValue($app->plugins, ['toy-car-rental' => [
            'operator_role_id' => ['setting_value' => json_encode($roleId)],
            'creator_notifications_enabled' => ['setting_value' => 'false'],
        ]]);
        $before = count(\App\Services\NotificationService::$dispatches);
        $notify->invoke(null, ['id' => 17, 'car_id' => 8]);
        $verify(count(\App\Services\NotificationService::$dispatches) === $before, 'No role and no creator copy must mean no dispatch.');
    }
    $settingsCache->setValue($app->plugins, ['toy-car-rental' => ['operator_role_id' => ['setting_value' => '7']]]);
    $database->roles[7]['slug'] = 'renamed-operator';
    $database->users[5] = 'renamed-operator';
    $notify->invoke(null, ['id' => 17, 'car_id' => 8]);
    $verify(end(\App\Services\NotificationService::$dispatches)['users'] === [1, 5], 'Role renaming must preserve the correct recipients.');
    $database->roles[4] = ['id' => 4, 'name' => 'Moderator', 'slug' => 'moderator'];
    $settingsCache->setValue($app->plugins, ['toy-car-rental' => ['operator_role_id' => ['setting_value' => '4']]]);
    $database->locales[3] = $database->locales[1];
    $notify->invoke(null, ['id' => 17, 'car_id' => 8]);
    $verify(end(\App\Services\NotificationService::$dispatches)['users'] === [1, 3], 'Moderators receive rental alerts only when explicitly selected.');

    $settingsCache->setValue($app->plugins, ['toy-car-rental' => ['operator_role_id' => ['setting_value' => '7']]]);
    $database->locales = [1 => 'ru', 5 => 'en'];
    $app->set('lang', LANGS['de']);
    \FBL\Language::$lang_data = require ROOT . '/plugins/toy-car-rental/lang/de.php';
    $before = count(\App\Services\NotificationService::$dispatches);
    $notify->invoke(null, ['id' => 17, 'car_id' => 8, 'car_name' => 'Test car', 'car_number' => '2']);
    $groups = array_slice(\App\Services\NotificationService::$dispatches, $before);
    $verify(count($groups) === 2, 'Recipients with different languages need localized push payloads.');
    foreach ($groups as $group) {
        $language = $group['users'] === [1] ? 'ru' : 'en';
        $translations = require ROOT . '/plugins/toy-car-rental/lang/' . $language . '.php';
        $verify($group['users'] === ($language === 'ru' ? [1] : [5]), 'Language grouping must not expand the audience.');
        $verify($group['payload']['title'] === $translations['toy_rental_notification_overdue_title'], 'Push must follow the recipient language instead of the requester language.');
        $verify($group['payload']['message'] === str_replace(':car', 'Test car №2', $translations['toy_rental_time_up_message']), 'Localize the push body for each recipient.');
    }
    $feed = \FireballPluginToyCarRental::localizeNotificationSource([], ['source' => 'toy-car-rental', 'metadata' => $groups[0]['payload']['metadata']]);
    $verify($feed['title'] === \FireballPluginToyCarRental::t('toy_rental_notification_overdue_title'), 'Stored alerts must retranslate after changing CMS language.');
    $verify(\FBL\Localization::currentLocale() === 'de', 'Sending localized push must not change the request language.');
    $legacy = ['title' => 'Previously saved title', 'text' => 'Previously saved text'];
    $feed = \FireballPluginToyCarRental::localizeNotificationSource($legacy, ['source' => 'toy-car-rental', 'metadata' => '{invalid']);
    $verify($feed['title'] === $legacy['title'] && $feed['text'] === $legacy['text'], 'Legacy alerts without context must retain their text.');
    echo "$checks rental notification checks passed; no real push sent.\n";
}
