<?php
declare(strict_types=1);
// Real service SQL in isolated SQLite. No application DB, network or encryption keys.
require dirname(__DIR__) . '/vendor/autoload.php';
define('PAGINATION_SETTINGS', ['perPage' => 20, 'midSize' => 2, 'maxPages' => 7, 'tpl' => 'pagination/base']);
$translations = require dirname(__DIR__) . '/app/Languages/ru.php';
function request(): object { return $GLOBALS['testRequest'] ??= new class {
    public string $uri = '/en/admin/settings/pwa?devices_page=2';
    public array $get = ['devices_page' => 2];
    public array $post = [];
    public function get(string $key, mixed $default = null): mixed { return $this->get[$key] ?? $default; }
    public function post(string $key, mixed $default = null): mixed { return $this->post[$key] ?? $default; }
}; }
function abort(string $error = '', int $code = 404): never { throw new RuntimeException($error ?: 'Aborted', $code); }
function base_href(string $path): string { return $path; }
function return_translation(string $key): string { return $GLOBALS['translations'][$key] ?? $key; }
function apply_filters_safe(string $name, mixed $value, mixed ...$args): mixed { return $value; }
function fireball_event(string $name, array $value): void { $GLOBALS['testEvents'][] = [$name, $value]; }
function session(): object { return new class {
    public function setFlash(string $key, mixed $value): void { $GLOBALS['testFlash'] = [$key, $value]; }
}; }
function response(): object { return new class {
    public function redirect(string $url): never { throw new RuntimeException($url, 302); }
}; }
function log_error_details(string $message, array $context, ?Throwable $exception = null): void { $GLOBALS['testErrors'][] = $message; }
final class NotificationPrivacyTestDb {
    public PDO $pdo;
    public bool $failCleanup = false;
    public bool $failLogClear = false;
    private ?PDOStatement $lastStatement = null;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE pwa_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, notification_id INTEGER, user_id INTEGER, title TEXT, body TEXT, type TEXT, source TEXT, payload TEXT, status TEXT, sent_count INTEGER, failed_count INTEGER, created_at TEXT, sent_at TEXT)');
        $this->pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT, message TEXT, type TEXT, action_url TEXT, icon TEXT, source TEXT, priority TEXT, metadata TEXT, is_read INTEGER, read_at TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE notification_settings (user_id INTEGER, push_enabled INTEGER)');
        $this->pdo->exec('CREATE TABLE pwa_subscriptions (id INTEGER, user_id INTEGER, is_active INTEGER, revoked_at TEXT, last_seen_at TEXT, endpoint TEXT)');
        $this->pdo->exec("INSERT INTO notification_settings VALUES (7, 1), (8, 0)");
        $this->pdo->exec("INSERT INTO pwa_subscriptions VALUES (1, 7, 1, NULL, '2026-10-05', 'https://web.push.apple.com/fixture')");
        $this->pdo->exec("CREATE TABLE chat_messages (id INTEGER, message_ciphertext TEXT)");
        $this->pdo->exec("INSERT INTO chat_messages VALUES (1, 'existing-encrypted-message-fixture')");
    }
    public function query(string $sql, array $params = []): object {
        if ($this->failCleanup && str_starts_with($sql, 'UPDATE notifications SET')) throw new RuntimeException('Cleanup failure');
        if ($this->failLogClear && $sql === 'DELETE FROM pwa_notifications') throw new RuntimeException('Log clear failure');
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $this->lastStatement = $statement;
        return new class($statement) {
            public function __construct(private PDOStatement $statement) {}
            public function getColumn(): mixed { return $this->statement->fetchColumn(); }
            public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
        };
    }
    public function getInsertId(): string { return $this->pdo->lastInsertId(); }
    public function rowCount(): int { return $this->lastStatement->rowCount(); }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
}
function db(): NotificationPrivacyTestDb { static $db; return $db ??= new NotificationPrivacyTestDb(); }
final class PrivacyPushTestService extends App\Services\PwaService {
    public bool $enabled = true;
    public bool $deliveryFails = false;
    public array $delivered = [];
    protected function normalizePayload(array $payload): array { return $payload; }
    protected function canSendPush(): bool { return $this->enabled; }
    protected function sendWebPush(array $subscription, array $payload): string {
        $this->delivered[] = $payload;
        return $this->deliveryFails ? 'invalid' : 'sent';
    }
}
$checks = 0;
function check(bool $value, string $label): void {
    $GLOBALS['checks']++;
    if (!$value) throw new RuntimeException($label);
}
$privateTitle = 'PRIVATE_SENDER_NAME';
$privateText = 'PRIVATE_BODY_🙂_attachment.pdf';
$privatePayload = [
    'title' => $privateTitle, 'body' => $privateText, 'type' => 'chat', 'source' => 'chat',
    'image' => 'https://fixture.test/private-attachment.jpg', 'url' => '/chat?user_id=42',
    'data' => ['preview' => $privateText, 'attachment_name' => 'private-attachment.pdf', 'sender_name' => $privateTitle],
];
$pwa = new PrivacyPushTestService();
$result = $pwa->send($privatePayload, ['user_id' => 7, 'notification_id' => 1]);
check($result['sent'] === 1 && $pwa->delivered[0]['body'] === $privateText && $pwa->delivered[0]['title'] === $privateTitle, 'Recipient Push preview is unchanged');
check($pwa->delivered[0]['data']['user_id'] === 7, 'Delivery remains targeted to recipient');
$pwa->enabled = false;
$pwa->send($privatePayload, ['user_id' => 7]);
$pwa->enabled = true;
$pwa->send($privatePayload, ['user_id' => 8]);
$pwa->deliveryFails = true;
$pwa->send($privatePayload, ['user_id' => 7]);
$pwa->deliveryFails = false;
$pwa->send(array_replace($privatePayload, ['type' => 'system', 'source' => 'system', 'data' => ['source' => 'chat', 'secret' => $privateText]]), ['user_id' => 7, 'type' => 'system']);
$storedLogs = db()->query('SELECT * FROM pwa_notifications')->get();
check(count($storedLogs) === 5, 'All delivery outcomes retain a technical record');
foreach ($storedLogs as $row) {
    check($row['title'] === App\Services\NotificationPrivacy::PRIVATE_TITLE && $row['body'] === '' && $row['payload'] === '{}', 'No chat title, body, metadata, URL or attachment in stored log');
    check(!str_contains(json_encode($row), 'PRIVATE_') && !str_contains(json_encode($row), 'private-attachment'), 'Log has no plaintext copy');
}
check(array_column($storedLogs, 'status') === ['sent', 'disabled', 'user_disabled', 'invalid', 'sent'], 'Status and counts preserved on all paths');

$service = new App\Services\NotificationService();
$chat = $service->createNotification([
    'user_id' => 7, 'title' => $privateTitle, 'message' => $privateText, 'type' => 'chat', 'source' => 'chat',
    'metadata' => ['sender_id' => 42, 'preview' => $privateText, 'filename' => 'private-attachment.pdf'],
    'action_url' => '/chat?user_id=42', 'store_unread' => false, 'push_notification' => false,
]);
$notificationRow = db()->query('SELECT * FROM notifications WHERE id = ?', [$chat['notification']['id']])->get()[0];
check($notificationRow['title'] === App\Services\NotificationPrivacy::PRIVATE_TITLE && $notificationRow['message'] === '' && $notificationRow['metadata'] === '{}', 'Notification storage is not a second plaintext transcript');
check($notificationRow['is_read'] === 1 && $notificationRow['action_url'] === '/chat?user_id=42', 'Read state and recipient action remain unchanged');
check($GLOBALS['testEvents'][0][1]['message'] === $privateText && $chat['notification']['title'] === $privateTitle, 'Live recipient event retains preview');
$push = (new ReflectionMethod($service, 'pushPayload'))->invoke($service, $chat['notification']);
check($push['body'] === $privateText && $push['title'] === $privateTitle, 'Live event/Push do not use redacted storage placeholder');
$service->createNotification([
    'user_id' => 7, 'title' => 'Public update', 'message' => 'Public update body', 'type' => 'system', 'source' => 'system',
    'metadata' => ['version' => '1.2'], 'push_notification' => false,
]);
$systemRow = db()->query("SELECT * FROM notifications WHERE type = 'system'")->get()[0];
check($systemRow['title'] === 'Public update' && $systemRow['message'] === 'Public update body' && json_decode($systemRow['metadata'], true)['version'] === '1.2', 'Ordinary notification storage is unchanged');

// Pre-fix history: plaintext is deliberately seeded ONLY inside this isolated database.
$tokens = ['chat', 'CHAT', 'chat_message', 'Chat2', 'group_chat', 'group-chat', 'groupchat', ' Chat '];
db()->query('INSERT INTO notifications (user_id, title, message, type, source, metadata, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    [8, $privateTitle, $privateText, 'chat', 'chat', json_encode(['preview' => $privateText]), 0, '2026-10-05']);
$legacyNotificationId = (int)db()->getInsertId();
$legacyNotification = db()->query('SELECT * FROM notifications WHERE id = ?', [$legacyNotificationId])->get()[0];
$legacyFeed = (new ReflectionMethod($service, 'feedItem'))->invoke($service, $legacyNotification);
check(!str_contains(json_encode($legacyFeed), 'PRIVATE_') && $legacyFeed['text'] === return_translation('notification_chat_private_hint'), 'Legacy persisted notification feed uses a generic private label');
check(!in_array($legacyNotificationId, array_column($service->unreadItemsForUser(7), 'notification_id'), true), 'Other recipient notification never appears in user feed');
for ($i = 0; $i < 124; $i++) {
    $token = $tokens[$i % count($tokens)];
    db()->query('INSERT INTO pwa_notifications (title, body, type, source, payload, status, sent_count, failed_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$privateTitle, $privateText, $i % 2 ? 'system' : $token, $i % 2 ? $token : 'system', json_encode($privatePayload), 'sent', 2, 0, '2026-10-05']);
}
db()->query("INSERT INTO pwa_notifications (title, body, type, source, payload, status, sent_count, failed_count, created_at) VALUES ('Public update', 'Public update body', 'system', 'system', '{}', 'sent', 1, 0, '2026-10-05')");
$first = $pwa->paginatedNotifications();
check($first['total'] === 130 && count($first['items']) === 20 && $first['pagination']['per_page'] === 20, 'Push history has real 20-row server pagination');
foreach ($first['items'] as $row) {
    if (!$row['private_chat']) continue;
    check($row['title'] === App\Services\NotificationPrivacy::PRIVATE_TITLE && $row['body'] === null, 'Legacy private text redacted at SQL boundary');
    check(!isset($row['payload']) && !isset($row['user_id']) && !isset($row['notification_id']), 'No raw payload or recipient identifier in admin query');
}
check(!str_contains(json_encode($first['items']), 'PRIVATE_'), 'Whole admin result is free of private previews');
check($first['items'][0]['body'] === 'Public update body', 'Ordinary history text still visible');
request()->get['notifications_page'] = 2;
request()->uri .= '&notifications_page=2';
$pageTwo = $pwa->paginatedNotifications();
check(count($pageTwo['items']) === 20 && $pageTwo['pagination']['current_page'] === 2, 'notifications_page drives the log, not devices_page');
check($pageTwo['pagination']['prev_url'] === '/en/admin/settings/pwa?devices_page=2', 'Log pagination retains independent devices_page and locale');
request()->get['notifications_page'] = 7;
$last = $pwa->paginatedNotifications();
check(count($last['items']) === 10, 'Last log page has remaining rows');
check(!str_contains(json_encode($pwa->recentNotifications(100)), 'PRIVATE_'), 'Compatibility read path is protected too');

$cleanup = require dirname(__DIR__) . '/database/migrations/20261005_redact_chat_notification_copies.php';
db()->failCleanup = true;
try { $cleanup(); check(false, 'Cleanup must fail in simulation'); }
catch (RuntimeException $exception) { check($exception->getMessage() === 'Cleanup failure', 'Cleanup failure propagated'); }
check(db()->query('SELECT COUNT(*) FROM pwa_notifications WHERE title = ?', [$privateTitle])->getColumn() === 124, 'Failed cleanup rolls back legacy log changes');
check(db()->query('SELECT message FROM notifications WHERE id = ?', [$legacyNotificationId])->getColumn() === $privateText, 'Failed cleanup rolls back both storage tables');
db()->failCleanup = false;
$cleanup();
$cleanup(); // Retry/idempotence.
check(db()->query('SELECT COUNT(*) FROM pwa_notifications WHERE title = ?', [$privateTitle])->getColumn() === 0, 'Migration scrubs historical title/body/payload copies');
check(db()->query('SELECT COUNT(*) FROM pwa_notifications')->getColumn() === 130, 'Delivery history rows/counts are retained');
$cleanedLegacy = db()->query('SELECT * FROM notifications WHERE id = ?', [$legacyNotificationId])->get()[0];
check($cleanedLegacy['title'] === App\Services\NotificationPrivacy::PRIVATE_TITLE && $cleanedLegacy['message'] === '' && $cleanedLegacy['metadata'] === '{}', 'Legacy notification title/message/metadata scrubbed too');
check((int)$cleanedLegacy['user_id'] === 8 && (int)$cleanedLegacy['is_read'] === 0, 'Recipient and unread state preserved by migration');
check(db()->query("SELECT body FROM pwa_notifications WHERE type = 'system' AND source = 'system'")->getColumn() === 'Public update body', 'Migration does not touch ordinary messages');
check(db()->query('SELECT message_ciphertext FROM chat_messages')->getColumn() === 'existing-encrypted-message-fixture', 'Encrypted chat history stays untouched');
check(db()->query("SELECT message FROM notifications WHERE type = 'system'")->getColumn() === 'Public update body', 'System notification contents remain unchanged');
foreach ($tokens as $token) check(App\Services\NotificationPrivacy::isChat(['type' => $token]), 'PHP/SQL privacy token coverage: ' . $token);

$controllerReflection = new ReflectionClass(App\Controllers\AdminController::class);
$controller = $controllerReflection->newInstanceWithoutConstructor();
$controllerReflection->getProperty('siteSettings')->setValue($controller, new App\Models\SiteSetting());
foreach ([[], ['scope' => 'one'], ['scope' => 'alll'], ['scope' => ['all']]] as $post) {
    request()->post = $post;
    try { $controller->clearPwaNotificationLog(); check(false, 'Malformed clear request must fail'); }
    catch (RuntimeException $exception) { check($exception->getCode() === 422, 'Malformed clear returns 422'); }
}
check(db()->query('SELECT COUNT(*) FROM pwa_notifications')->getColumn() === 130, 'Malformed requests cannot clear history');
$notificationsBefore = db()->query('SELECT * FROM notifications')->get();
$devicesBefore = db()->query('SELECT * FROM pwa_subscriptions')->get();
request()->post = ['scope' => 'all', 'devices_page' => 2];
db()->failLogClear = true;
try { $controller->clearPwaNotificationLog(); check(false, 'Expected error redirect'); }
catch (RuntimeException $exception) { check($exception->getCode() === 302, 'Failed clear redirects safely'); }
check($GLOBALS['testFlash'][0] === 'error' && db()->query('SELECT COUNT(*) FROM pwa_notifications')->getColumn() === 130, 'Failed clear keeps rows and shows an error');
db()->failLogClear = false;
try { $controller->clearPwaNotificationLog(); check(false, 'Expected clear redirect'); }
catch (RuntimeException $exception) { check($exception->getCode() === 302 && $exception->getMessage() === '/admin/settings/pwa?devices_page=2#pwa-notifications', 'Clear resets log page only and returns to journal'); }
check(db()->query('SELECT COUNT(*) FROM pwa_notifications')->getColumn() === 0, 'Clear removes entire log, not just a page');
check($GLOBALS['testFlash'][0] === 'success' && str_contains($GLOBALS['testFlash'][1], '130'), 'Clear reports real affected-row count');
check(db()->query('SELECT * FROM notifications')->get() === $notificationsBefore, 'Clear retains user notification records');
check(db()->query('SELECT * FROM pwa_subscriptions')->get() === $devicesBefore, 'Clear retains device bindings');
check(db()->query('SELECT message_ciphertext FROM chat_messages')->getColumn() === 'existing-encrypted-message-fixture', 'Clear never deletes encrypted messages');
check($pwa->clearNotificationLog() === 0, 'Empty log clear is idempotent');
request()->get = ['devices_page' => 2];
check($pwa->paginatedNotifications()['total'] === 0 && $pwa->paginatedNotifications()['items'] === [], 'Empty log pagination works');
$routes = file_get_contents(dirname(__DIR__) . '/config/routes.php');
check(str_contains($routes, "->post('/admin/settings/pwa/notifications/clear', [AdminController::class, 'clearPwaNotificationLog'])->middleware(['auth', 'admin'])"), 'Clear route is POST-only and admin-protected');
check(!str_contains($routes, "'clearPwaNotificationLog'])->middleware(['auth', 'admin'])->withoutCSRFToken"), 'Clear uses normal route CSRF protection');

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $labels = require dirname(__DIR__) . '/app/Languages/' . $locale . '.php';
    foreach (['admin_pwa_private_chat_title', 'admin_pwa_private_chat_hint', 'admin_pwa_notifications_privacy_hint', 'notification_chat_private_hint', 'admin_pwa_notifications_delivery_hint', 'admin_pwa_notifications_clear', 'admin_pwa_notifications_clear_confirm', 'admin_pwa_notifications_clear_hint', 'admin_pwa_notifications_cleared', 'admin_pwa_notifications_clear_error'] as $key) check(!empty($labels[$key]), 'Localized privacy/log label ' . $locale);
    foreach (['queued', 'sent', 'partial', 'failed', 'invalid', 'no_subscriptions', 'disabled', 'user_disabled'] as $status) check(!empty($labels['admin_pwa_notification_status_' . $status]), 'Localized delivery state ' . $locale);
}
echo 'PWA notification privacy checks passed: ' . $checks . PHP_EOL;
