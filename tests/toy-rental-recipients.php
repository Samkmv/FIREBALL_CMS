<?php
declare(strict_types=1);

// Exercise real settings persistence and recipient SQL against isolated SQLite;
// replace only delivery so no live website records or device pushes are created.
namespace App\Services {
    final class NotificationService {
        public static array $calls = [];
        public static function createForUsers(array $ids, array $payload): void { self::$calls[] = compact('ids', 'payload'); }
    }
}
namespace {
    $project = dirname(__DIR__);
    require $project . '/vendor/autoload.php';
    $scratch = __DIR__ . '/.tmp/toy-rental-recipients-' . bin2hex(random_bytes(8));
    mkdir($scratch, 0700, true);
    define('ROOT', $project);
    define('STORAGE', $scratch . '/storage');
    define('LANGS', array_fill_keys(['ru', 'en', 'de', 'zh-cn'], []));
    define('DEFAULT_LOCALE', 'ru');

    final class RecipientDatabase extends \FBL\Database {
        public ?string $failSetting = null;
        public function __construct(PDO $connection) { $this->connection = $connection; }
        public function query(string $query, array $params = []): static {
            if (str_contains($query, 'ON DUPLICATE KEY UPDATE')) {
                if (($params[1] ?? '') === $this->failSetting) throw new RuntimeException('Simulated settings write failure');
                $query = 'INSERT INTO plugin_settings VALUES (?,?,?,?) ON CONFLICT(plugin_slug,setting_key) DO UPDATE SET setting_value=excluded.setting_value,updated_at=excluded.updated_at';
            }
            $this->stmt = $this->connection->prepare($query);
            $this->stmt->execute($params);
            return $this;
        }
    }
    $pdo = new PDO('sqlite:' . $scratch . '/fixture.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $GLOBALS['recipientDb'] = new RecipientDatabase($pdo);
    $GLOBALS['recipientManager'] = new \FBL\Plugins\PluginManager($project . '/plugins');
    (new ReflectionProperty($GLOBALS['recipientManager'], 'schemaReady'))->setValue($GLOBALS['recipientManager'], true);
    $GLOBALS['recipientApp'] = new class {
        public mixed $db = null;
        public string $locale = 'ru';
        public function get(string $key): mixed { return $key === 'lang' ? ['code' => $this->locale] : null; }
    };
    $GLOBALS['recipientUser'] = [];
    $GLOBALS['recipientErrors'] = [];
    function db(): \FBL\Database { return $GLOBALS['recipientDb']; }
    function plugin_setting(string $slug, string $key, mixed $default = null): mixed { return $GLOBALS['recipientManager']->setting($slug, $key, $default); }
    function plugin_setting_set(string $slug, string $key, mixed $value): void { $GLOBALS['recipientManager']->setSetting($slug, $key, $value); }
    function app(): object { return $GLOBALS['recipientApp']; }
    function get_user(): array { return $GLOBALS['recipientUser']; }
    function base_href(string $path): string { return $path; }
    function uri_without_lang(): string { return '/admin/toy-rental/settings'; }
    function log_error_details(string $message, array $context = [], ?Throwable $error = null): void { $GLOBALS['recipientErrors'][] = [$message, $error?->getMessage()]; }
    require $project . '/plugins/toy-car-rental/Plugin.php';
    $checks = 0;
    function check(string $label, bool $success): void { global $checks; if (!$success) throw new RuntimeException($label); $checks++; }
    function rejects(callable $operation): bool { try { $operation(); return false; } catch (RuntimeException) { return true; } }
    function idsDelivered(): array { $ids = []; foreach (\App\Services\NotificationService::$calls as $call) $ids = array_merge($ids, $call['ids']); sort($ids); return $ids; }
    $dispatchMethod = new ReflectionMethod(FireballPluginToyCarRental::class, 'notifyOperatorsAboutOverdueRide');
    $dispatch = static function (bool $limit = false) use ($dispatchMethod): void {
        \App\Services\NotificationService::$calls = [];
        $dispatchMethod->invoke(null, ['id' => 14, 'car_id' => 4, 'car_name' => 'Jeep', 'car_number' => '7'], $limit);
    };
    try {
        $pdo->exec('CREATE TABLE plugin_settings (plugin_slug TEXT, setting_key TEXT, setting_value TEXT, updated_at TEXT, PRIMARY KEY(plugin_slug,setting_key))');
        $pdo->exec('CREATE TABLE user_roles (id INTEGER PRIMARY KEY,name TEXT,slug TEXT)');
        $pdo->exec("INSERT INTO user_roles VALUES (1,'Создатель','creator'),(2,'Администратор','admin'),(3,'Модератор','moderator'),(4,'Операторы <A>','rental'),(5,'Пользователь','user')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY,name TEXT,login TEXT,role TEXT,locale TEXT)');
        $pdo->exec("INSERT INTO users VALUES (1,'Kirill','owner','creator','ru'),(2,'Admin','admin','admin','en'),(3,'Operator','operator','rental','de'),(4,'Member','member','user','en'),(5,'Another operator','operator2','rental','zh-cn'),(6,'Moderator','mod','moderator','unsupported')");
        plugin_setting_set('toy-car-rental', 'operator_role_id', 4);
        $users = FireballPluginToyCarRental::notificationUsers();
        check('All user roles available without granting access', count($users) === 6 && FireballPluginToyCarRental::canOperate(['id'=>3,'role'=>'rental']) && !FireballPluginToyCarRental::canOperate(['id'=>4,'role'=>'user']));
        check('Custom role name joins correctly', FireballPluginToyCarRental::notificationUserRoleLabel(array_values(array_filter($users, fn($u)=>$u['id']===3))[0]) === 'Операторы <A>');
        $legacyIds = FireballPluginToyCarRental::selectedNotificationUserIds(); sort($legacyIds);
        check('Legacy recipients are preselected before save', $legacyIds === [1,3,5]);
        $dispatch();
        check('Legacy dispatch preserves operator role and creator copy', idsDelivered() === [1,3,5]);
        $data = ['operator_role_id'=>4,'fixed_durations'=>'5,10,15,30','default_duration'=>10,'max_ride_minutes'=>120,'default_minute_price'=>20,'sound_enabled'=>1,'overdue_push_enabled'=>1,'notification_recipients_present'=>1,'notification_recipients_complete'=>1,'notification_user_ids'=>['4','3','3']];
        FireballPluginToyCarRental::saveSettings($data);
        check('Explicit IDs persist once without duplicates', FireballPluginToyCarRental::settings()['notification_user_ids'] === [4,3]);
        $truncated = $data; unset($truncated['notification_recipients_complete']);
        check('Truncated form cannot silently discard recipients', rejects(fn()=>FireballPluginToyCarRental::saveSettings($truncated)) && FireballPluginToyCarRental::settings()['notification_user_ids'] === [4,3]);
        $dispatch();
        check('Only exact selected users receive delivery, no implicit creator or second operator', idsDelivered() === [3,4]);
        foreach (\App\Services\NotificationService::$calls as $call) {
            $payload = $call['payload'];
            check('Both site and push use the same selected users', $payload['store_unread'] === true && $payload['push_notification'] === true);
            check('Links respect existing operator access', $payload['action_url'] === ($call['ids'] === [3] ? '/profile/toy-rental' : '/profile'));
            $locale = $call['ids'] === [3] ? 'de' : 'en';
            check('Notification language follows recipient locale', $payload['title'] === FireballPluginToyCarRental::t('toy_rental_notification_overdue_title', [], $locale));
        }
        check('Selected member still has no operator access', !FireballPluginToyCarRental::canOperate(['id'=>4,'role'=>'user']));
        FireballPluginToyCarRental::saveSettings(array_replace($data, ['overdue_push_enabled'=>0]));
        $dispatch(true);
        check('Limit notification keeps same recipient list', idsDelivered() === [3,4]);
        check('Disabling push retains site notification', count(array_filter(\App\Services\NotificationService::$calls, fn($c)=>$c['payload']['store_unread'] && !$c['payload']['push_notification'] && $c['payload']['metadata']['event']==='limit_reached')) === 2);
        foreach (['4', [9999], ['-1'], ['x'], [['3']]] as $invalid) {
            check('Reject malformed or missing recipients', rejects(fn()=>FireballPluginToyCarRental::saveSettings(array_replace($data,['notification_user_ids'=>$invalid]))));
            check('Failed validation preserves saved recipients', FireballPluginToyCarRental::settings()['notification_user_ids'] === [4,3]);
        }
        $withoutMarker = $data; unset($withoutMarker['notification_recipients_present']);
        FireballPluginToyCarRental::saveSettings(array_replace($withoutMarker, ['notification_user_ids'=>[1]]));
        check('Older callers preserve saved selection', FireballPluginToyCarRental::settings()['notification_user_ids'] === [4,3]);
        $pdo->exec('DELETE FROM users WHERE id=4');
        $dispatch();
        check('Deleted selected users are skipped', idsDelivered() === [3]);
        FireballPluginToyCarRental::saveSettings(array_replace($data, ['notification_user_ids'=>[]]));
        $dispatch();
        check('Empty saved selection sends nobody notifications', idsDelivered() === [] && FireballPluginToyCarRental::selectedNotificationUserIds() === []);
        plugin_setting_set('toy-car-rental','creator_notifications_enabled',true);
        $dispatch();
        check('Legacy creator flag never overrides explicit empty selection', idsDelivered() === []);
        $before = FireballPluginToyCarRental::settings();
        $GLOBALS['recipientDb']->failSetting = 'notification_user_ids';
        check('Settings failure rolls back transaction', rejects(fn()=>FireballPluginToyCarRental::saveSettings(array_replace($data,['notification_user_ids'=>[1],'default_minute_price'=>999]))) && !db()->inTransaction());
        check('No partial settings persist after rollback', FireballPluginToyCarRental::settings() === $before);
        $GLOBALS['recipientDb']->failSetting = null;
        $owned = str_repeat('a',40) . '.wav';
        mkdir(STORAGE . '/toy-car-rental/sounds',0700,true);
        file_put_contents(STORAGE . '/toy-car-rental/sounds/' . $owned, 'owned sound fixture');
        plugin_setting_set('toy-car-rental','custom_sound_file',$owned);
        plugin_setting_set('toy-car-rental','custom_sound_name','my.wav');
        check('Custom URL is authenticated route with version key', FireballPluginToyCarRental::customSoundUrl() === '/admin/toy-rental/sound?v=' . $owned);
        FireballPluginToyCarRental::saveSettings(array_replace($withoutMarker,['custom_sound_file'=>'../../outside.mp3','custom_sound_name'=>'evil']));
        check('POST cannot replace private audio path directly', FireballPluginToyCarRental::settings()['custom_sound_file'] === $owned);
        $GLOBALS['recipientDb']->failSetting = 'custom_sound_file';
        check('Failed removal preserves old audio', rejects(fn()=>FireballPluginToyCarRental::saveSettings(array_replace($withoutMarker,['remove_custom_sound'=>1]))) && file_exists(STORAGE . '/toy-car-rental/sounds/' . $owned));
        check('Failed removal preserves old audio setting', FireballPluginToyCarRental::settings()['custom_sound_file'] === $owned);
        $GLOBALS['recipientDb']->failSetting = null;
        FireballPluginToyCarRental::saveSettings(array_replace($withoutMarker,['remove_custom_sound'=>1]));
        check('Successful reset removes former file and setting', !file_exists(STORAGE . '/toy-car-rental/sounds/' . $owned) && FireballPluginToyCarRental::customSoundUrl() === '');
        foreach (['ru','en','de','zh-cn'] as $locale) {
            app()->locale = $locale;
            check('Built-in role label is localized ' . $locale, !str_starts_with(FireballPluginToyCarRental::notificationUserRoleLabel(['role'=>'creator']), 'toy_rental_'));
        }
        $GLOBALS['recipientUser'] = ['id'=>1,'role'=>'creator'];
        check('Creator feed item opens operator panel', FireballPluginToyCarRental::localizeNotificationSource([],['source'=>'toy-car-rental'])['url'] === '/profile/toy-rental');
        $GLOBALS['recipientUser'] = ['id'=>6,'role'=>'moderator'];
        check('Selected nonoperator feed item avoids forbidden panel', FireballPluginToyCarRental::localizeNotificationSource([],['source'=>'toy-car-rental'])['url'] === '/profile');
        check('No swallowed dispatch errors', $GLOBALS['recipientErrors'] === []);
        echo "Rental recipient and settings checks passed: $checks\n";
    } finally {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($scratch);
    }
}
