<?php

declare(strict_types=1);

// Reuse the isolated SQL/session harness; no production database or GitHub calls.
require __DIR__ . '/admin_permissions_unit.php';

use App\Models\ChatMessage;
use App\Models\ContactRequest;
use App\Models\NotificationCenter;
use App\Services\NotificationService;

final class FeedTestChat extends ChatMessage
{
    public bool $read = false;
    public function getUnreadCountForUser(int $userId): int { return $this->read ? 0 : 3; }
    public function getUnreadNotificationItemsForUser(int $userId, int $limit = 8): array
    {
        return $this->read ? [] : [['type' => 'chat', 'sender_id' => 9, 'sort_id' => 9, 'created_at' => '2026-01-01 00:00:00']];
    }
    public function markAllAsReadForUser(int $userId): int { $this->read = true; return 3; }
}
final class FeedTestContacts extends ContactRequest
{
    public bool $read = false;
    public function countUnread(): int { return $this->read ? 0 : 1; }
    public function getUnreadNotificationItems(int $limit = 8): array
    {
        return $this->read ? [] : [['type' => 'contact_request', 'sort_id' => 7, 'created_at' => '2026-01-01 00:00:00']];
    }
    public function markAllViewed(): void { $this->read = true; }
}
final class FeedTestNotifications extends NotificationService
{
    public array $dismissed = [];
    public function ensureTables(): void {}
    public function isFeedItemDismissed(int $userId, array $item): bool { return isset($this->dismissed[$userId][$item['dismiss_key'] ?? '']); }
    public function dismissFeedItems(int $userId, array $items): int
    {
        foreach ($items as $item) $this->dismissed[$userId][$item['dismiss_key']] = true;
        return count($items);
    }
}
final class FeedTestCenter extends NotificationCenter
{
    public function __construct($chats, $contacts, $updates, $notifications)
    {
        $this->chatMessages = $chats; $this->contactRequests = $contacts;
        $this->updateCenter = $updates; $this->notifications = $notifications;
    }
}

$permissionAssertions = 0;
$chats = new FeedTestChat(); $contacts = new FeedTestContacts(); $notifications = new FeedTestNotifications();
$settings = new PermissionSettings(); $updater = new PermissionUpdater($settings);
$center = new FeedTestCenter($chats, $contacts, $updater, $notifications);
$version = (require CONFIG . '/version.php')['version'];
$stable = ['status' => 'ok', 'channel' => 'stable', 'local_version' => $version, 'update_available' => true, 'remote_version' => '999.0.0', 'checked_at' => date('Y-m-d H:i:s')];
$dev = array_replace($stable, ['channel' => 'dev', 'remote_version' => '999.1.0-dev']);
$settings->setMany(['updater_last_check_payload' => json_encode($dev), 'updater_last_check_payload_stable' => json_encode($stable), 'updater_last_check_payload_dev' => json_encode($dev)]);

db()->query('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT, message TEXT, action_url TEXT, is_read INTEGER DEFAULT 0, read_at TEXT, created_at TEXT)');
db()->query('INSERT INTO notifications (id, user_id, title, message, action_url, created_at) VALUES (1, 2, ?, ?, ?, ?)', ['Full title', "Full message\nSecond line", '/admin/updates', '2026-09-01 00:00:00']);
db()->query('INSERT INTO notifications (id, user_id, title, created_at) VALUES (2, 5, ?, ?)', ['Private message', '2026-09-01 00:00:00']);
session()->set('user', ['id' => 2, 'role' => 'admin']);
$feed = $center->getFeedForUser(2, true);
permissionAssert($feed['update_unread_count'] === 1, 'Admin does not receive update notification');
permissionAssert(count(array_filter($feed['items'], fn($item) => ($item['type'] ?? '') === 'update' && $item['title'] !== '' && str_contains($item['text'], '999.0.0'))) === 1, 'Admin did not receive Stable');
permissionAssert($updater->httpUrls === [], 'Separate Stable cache was not reused');
permissionAssert($feed['total_unread_count'] === 6, 'Unread count does not combine sources');
permissionAssert($center->badgeCountForUser(2, true) === $feed['total_unread_count'], 'PWA badge differs from notification bell');
permissionAssert($center->badgeCountForUser(0, false) === 0, 'Anonymous badge lookup is not empty');
permissionAssert($notifications->unreadItemsForUser(2)[0]['text'] === "Full message\nSecond line", 'Full message was truncated');
permissionAssert(!$notifications->markRead(2, 2), 'Can read another user notification');
permissionAssert($notifications->unreadCountForUser(5) === 1, 'Another user unread count changed');
permissionAssert($notifications->markRead(2, 1), 'Own notification cannot be marked read');
permissionAssert($notifications->unreadCountForUser(2) === 0, 'Own unread count did not decrease');
permissionAssert((int)db()->query('SELECT COUNT(*) FROM notifications')->getColumn() === 2, 'Reading removed notification history');

session()->set('user', ['id' => 1, 'role' => 'creator']);
$feed = $center->getFeedForUser(1, true);
permissionAssert(count(array_filter($feed['items'], fn($item) => ($item['type'] ?? '') === 'update' && str_contains($item['text'], '999.1.0-dev'))) === 1, 'Creator did not receive configured Dev');
session()->set('user', ['id' => 2, 'role' => 'admin']);
$center->getFeedForUser(2, true);
permissionAssert($updater->httpUrls === [], 'Alternating roles trigger repeated GitHub requests');
session()->set('user', ['id' => 5, 'role' => 'user']);
$feed = $center->getFeedForUser(5, false);
permissionAssert($feed['update_unread_count'] === 0 && $feed['contact_unread_count'] === 0, 'Non-admin received administrative notifications');
permissionAssert(count(array_filter($feed['items'], fn($item) => ($item['notification_id'] ?? 0) === 1)) === 0, 'Another user notification leaked');

// Twenty newer stored entries must not prevent clearing an older generated update.
session()->set('user', ['id' => 2, 'role' => 'admin']);
for ($id = 10; $id < 35; $id++) db()->query('INSERT INTO notifications (id, user_id, title, created_at) VALUES (?, 2, ?, ?)', [$id, 'Message ' . $id, '2099-01-01 00:00:00']);
$feed = $center->getFeedForUser(2, true);
permissionAssert(count($feed['items']) === 20, 'Dropdown still has eight-item limit');
permissionAssert($feed['items'][0]['notification_id'] === 34, 'Feed sorting is unstable');
permissionAssert($feed['update_unread_count'] === 1 && count($feed['dismissible_items']) === 1, 'Older generated update lost');
$center->clearForUser(2, true);
permissionAssert($center->getFeedForUser(2, true)['total_unread_count'] === 0, 'Clear left generated notifications or counters');
permissionAssert((int)db()->query('SELECT COUNT(*) FROM notifications')->getColumn() === 27, 'Clear removed history');
permissionAssert($notifications->unreadCountForUser(5) === 1, 'Clear modified another user');

$stale = array_replace($stable, ['checked_at' => '2000-01-01 00:00:00']);
$settings->setMany(['updater_last_check_payload_stable' => json_encode($stale), 'updater_last_checked_at' => date('Y-m-d H:i:s')]);
$updater->checkForUpdatesIfStale();
permissionAssert(count($updater->httpUrls) === 1, 'Dev timestamp masked an expired Stable result');
$settings->setMany(['updater_last_check_payload' => '', 'updater_last_check_payload_stable' => json_encode(array_replace($stable, ['local_version' => '0.0.0']))]);
permissionAssert($updater->getLastCheckPayload() === null, 'Cache from another installed version reused');
$requestsBeforeBadge = count($updater->httpUrls);
$center->badgeCountForUser(2, true);
permissionAssert(count($updater->httpUrls) === $requestsBeforeBadge, 'Background badge query started a GitHub check');

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    $translations = require ROOT . '/app/Languages/' . $locale . '.php';
    foreach (['notification_open', 'notification_mark_read', 'notification_load_error', 'notification_retry'] as $key) permissionAssert(!empty($translations[$key]), 'Missing translation: ' . $locale . '/' . $key);
}
$layout = file_get_contents(ROOT . '/app/Views/layouts/default.php');
$scripts = file_get_contents(ROOT . '/public/assets/default/js/main.js');
$styles = file_get_contents(ROOT . '/public/assets/default/css/style.css');
foreach (['data-bs-auto-close="outside"', 'notification-feed-list', 'role="region" aria-labelledby="notification-feed-title"', 'data-mark-read-label', 'data-load-error'] as $required) permissionAssert(str_contains($layout, $required), 'Missing notification UI contract: ' . $required);
foreach (['overflow-y: auto', 'overscroll-behavior: contain', '100dvh', '.notification-feed-header', '.notification-feed-item__text'] as $required) permissionAssert(str_contains($styles, $required), 'Missing scroll/reading style: ' . $required);
foreach (['list.scrollTop =', 'html === renderedNotificationHtml', "focus({ preventScroll: true })", 'notificationFeedRequest?.abort()', "notificationCenter.on('shown.bs.dropdown'", "window.addEventListener('online'", 'data-notifications-retry', 'safeNotificationUrl(button.attr'] as $required) permissionAssert(str_contains($scripts, $required), 'Missing feed interaction guard: ' . $required);
echo 'Notification center: ' . $permissionAssertions . " checks passed.\n";
