<?php

namespace App\Models;

use App\Services\UpdateCenter;

/**
 * Объединяет уведомления из разных источников в единую ленту для пользователя.
 */
class NotificationCenter
{

    protected ChatMessage $chatMessages;
    protected ContactRequest $contactRequests;
    protected UpdateCenter $updateCenter;

    /**
     * Инициализирует модели уведомлений по чатам и заявкам.
     */
    public function __construct()
    {
        $this->chatMessages = new ChatMessage();
        $this->contactRequests = new ContactRequest();
        $this->updateCenter = new UpdateCenter(new SiteSetting());
    }

    /**
     * Возвращает общую ленту непрочитанных уведомлений для пользователя.
     */
    public function getFeedForUser(int $userId, bool $isAdmin, int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $chatUnreadCount = $this->chatMessages->getUnreadCountForUser($userId);
        $contactUnreadCount = $isAdmin ? $this->contactRequests->countUnread() : 0;
        $updateItem = $isAdmin ? $this->getUpdateNotificationItem() : null;
        $updateUnreadCount = $updateItem !== null ? 1 : 0;

        $chatItems = $this->chatMessages->getUnreadNotificationItemsForUser($userId, $limit);
        $contactItems = $isAdmin ? $this->contactRequests->getUnreadNotificationItems($limit) : [];
        $items = array_merge($chatItems, $contactItems);
        if ($updateItem !== null) {
            $items[] = $updateItem;
        }

        usort($items, static function (array $left, array $right): int {
            $leftTime = strtotime((string)($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string)($right['created_at'] ?? '')) ?: 0;

            if ($leftTime === $rightTime) {
                return ((int)($right['sort_id'] ?? 0)) <=> ((int)($left['sort_id'] ?? 0));
            }

            return $rightTime <=> $leftTime;
        });

        return [
            'total_unread_count' => $chatUnreadCount + $contactUnreadCount + $updateUnreadCount,
            'chat_unread_count' => $chatUnreadCount,
            'contact_unread_count' => $contactUnreadCount,
            'update_unread_count' => $updateUnreadCount,
            'items' => array_slice($items, 0, $limit),
        ];
    }

    /**
     * Добавляет уведомление о новой версии, если авто-проверка нашла обновление.
     */
    protected function getUpdateNotificationItem(): ?array
    {
        $payload = $this->updateCenter->checkForUpdatesIfStale();
        if (!is_array($payload) || ($payload['status'] ?? '') !== 'ok' || empty($payload['update_available'])) {
            return null;
        }

        $release = is_array($payload['release'] ?? null) ? $payload['release'] : [];
        $version = trim((string)($payload['remote_version'] ?? ''));
        $releaseTitle = trim((string)(($release['name'] ?? '') !== '' ? $release['name'] : ($release['tag_name'] ?? '')));
        $title = $releaseTitle !== '' ? $releaseTitle : return_translation('notification_update_fallback_title');
        $text = $version !== ''
            ? str_replace(':version', $version, return_translation('notification_update_available'))
            : return_translation('notification_update_available_generic');

        return [
            'type' => 'update',
            'title' => $title,
            'text' => $text,
            'url' => base_href('/admin/updates#update-center'),
            'created_at' => (string)($payload['checked_at'] ?? date('Y-m-d H:i:s')),
            'sort_id' => PHP_INT_MAX,
        ];
    }

}
