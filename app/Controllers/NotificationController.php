<?php

namespace App\Controllers;

use App\Models\NotificationCenter;
use App\Services\NotificationService;

/**
 * Возвращает ленту уведомлений для текущего пользователя.
 */
class NotificationController extends BaseController
{

    protected NotificationCenter $notifications;

    /**
     * Инициализирует модель центра уведомлений.
     */
    public function __construct()
    {
        parent::__construct();
        $this->notifications = new NotificationCenter();
    }

    /**
     * Формирует JSON-ленту уведомлений по чатам и заявкам.
     */
    public function feed()
    {
        $currentUser = get_user();
        $feed = $this->notifications->getFeedForUser((int)$currentUser['id'], check_admin());

        $items = array_map(function (array $item): array {
            $type = (string)($item['type'] ?? '');
            $storedNotificationId = (int)($item['notification_id'] ?? 0);

            if ($storedNotificationId > 0) {
                return [
                    'type' => $type !== '' ? $type : 'system',
                    'notification_id' => $storedNotificationId,
                    'source_label' => (string)($item['source_label'] ?? return_translation('tpl_notifications')),
                    'title' => (string)($item['title'] ?? return_translation('tpl_notifications')),
                    'text' => (string)($item['text'] ?? ''),
                    'url' => (string)($item['url'] ?? '#'),
                    'created_at' => (string)($item['created_at'] ?? ''),
                    'time' => (string)($item['time'] ?? ($item['created_at'] ?? '')),
                    'sort_id' => (int)($item['sort_id'] ?? 0),
                ];
            }

            if ($type === 'update') {
                return [
                    'type' => 'update',
                    'source_label' => return_translation('notification_source_update'),
                    'title' => (string)($item['title'] ?? return_translation('notification_update_fallback_title')),
                    'text' => (string)($item['text'] ?? return_translation('notification_update_available_generic')),
                    'url' => (string)($item['url'] ?? base_href('/admin/updates')),
                    'created_at' => (string)($item['created_at'] ?? ''),
                ];
            }

            if ($type === 'contact_request') {
                return [
                    'type' => 'contact_request',
                    'source_label' => return_translation('notification_source_request'),
                    'title' => (string)($item['subject'] ?? return_translation('notification_request_fallback_subject')),
                    'text' => str_replace(':name', (string)($item['name'] ?? ''), return_translation('notification_request_from')),
                    'url' => base_href('/admin/contact-requests'),
                    'created_at' => (string)($item['created_at'] ?? ''),
                ];
            }

            if ($type !== '' && $type !== 'chat') {
                return [
                    'type' => $type,
                    'source_label' => (string)($item['source_label'] ?? return_translation('tpl_notifications')),
                    'title' => (string)($item['title'] ?? return_translation('tpl_notifications')),
                    'text' => (string)($item['text'] ?? ''),
                    'url' => (string)($item['url'] ?? '#'),
                    'created_at' => (string)($item['created_at'] ?? ''),
                    'time' => (string)($item['time'] ?? ($item['created_at'] ?? '')),
                    'sort_id' => (int)($item['sort_id'] ?? 0),
                ];
            }

            return [
                'type' => 'chat',
                'source_label' => return_translation('notification_source_chat'),
                'title' => (string)($item['name'] ?? return_translation('notification_chat_fallback_title')),
                'text' => (string)($item['preview'] ?? str_replace(':count', (string)((int)($item['unread_count'] ?? 0)), return_translation('notification_chat_unread_count'))),
                'avatar' => get_user_avatar($item['avatar'] ?? null, 'sm'),
                'sender_id' => (int)($item['sender_id'] ?? 0),
                'unread_count' => (int)($item['unread_count'] ?? 0),
                'sort_id' => (int)($item['sort_id'] ?? 0),
                'url' => base_href('/chat?user_id=' . (int)($item['sender_id'] ?? 0)),
                'created_at' => (string)($item['created_at'] ?? ''),
                'time' => (string)($item['time'] ?? ($item['created_at'] ?? '')),
            ];
        }, $feed['items']);

        response()->json([
            'status' => true,
            'total_unread_count' => (int)$feed['total_unread_count'],
            'notification_unread_count' => (int)($feed['notification_unread_count'] ?? 0),
            'chat_unread_count' => (int)$feed['chat_unread_count'],
            'contact_unread_count' => (int)$feed['contact_unread_count'],
            'plugin_unread_count' => (int)($feed['plugin_unread_count'] ?? 0),
            'items' => $items,
        ]);
    }

    public function markRead(): void
    {
        $notificationId = (int)request()->post('notification_id', 0);
        $ok = (new NotificationService())->markRead((int)get_user()['id'], $notificationId);

        response()->json(['status' => $ok]);
    }

}
