<?php

namespace Fireball\Subscriptions\Support;

final class NotificationPresentation
{
    public static function localize(array $item, array $row): array
    {
        if (($row['source'] ?? '') !== 'subscriptions') {
            return $item;
        }

        $item['source_label'] = \FireballPluginSubscriptions::t('subscriptions_menu');
        $metadata = $row['metadata'] ?? [];
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        $metadata = is_array($metadata) ? $metadata : [];
        $events = ['activated', 'expiring', 'recurring_failed'];
        $event = (string)($metadata['subscription_notification'] ?? '');
        if (!in_array($event, $events, true)) {
            $event = '';
            foreach ($events as $candidate) {
                if (($row['title'] ?? '') === 'subscriptions_notification_' . $candidate . '_title') {
                    $event = $candidate;
                    break;
                }
            }
        }
        if ($event === '') {
            return $item;
        }

        $prefix = 'subscriptions_notification_' . $event;
        $item['title'] = \FireballPluginSubscriptions::t($prefix . '_title');
        // Old cron notifications did not store the remaining days. Do not
        // invent a number or use today's remaining term for a past notice.
        if ($event === 'expiring' && !isset($metadata['days'])) {
            $existing = (string)($row['message'] ?? '');
            $item['text'] = $existing !== '' && $existing !== $prefix . '_message'
                ? $existing
                : \FireballPluginSubscriptions::t($prefix . '_message_generic');
        } else {
            $item['text'] = \FireballPluginSubscriptions::t($prefix . '_message');
            if ($event === 'expiring') {
                $item['text'] = str_replace(':days', (string)(int)$metadata['days'], $item['text']);
            }
        }

        return $item;
    }
}
