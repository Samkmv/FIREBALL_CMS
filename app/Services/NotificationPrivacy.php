<?php

namespace App\Services;

/** Chat previews may be delivered to the recipient, but must not become plaintext log copies. */
final class NotificationPrivacy
{
    public const PRIVATE_TITLE = 'Private message notification';

    public static function isChat(array $notification): bool
    {
        foreach (['type', 'source'] as $key) {
            $token = strtolower(trim((string)($notification[$key] ?? '')));
            foreach (['chat', 'group_chat', 'group-chat', 'groupchat'] as $prefix) {
                if (str_starts_with($token, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Used inside SELECT/UPDATE so historical private text never reaches an admin renderer. */
    public static function chatSqlPredicate(): string
    {
        $conditions = [];
        foreach (['type', 'source'] as $column) {
            foreach (['chat%', 'group_chat%', 'group-chat%', 'groupchat%'] as $pattern) {
                $conditions[] = "LOWER(TRIM(COALESCE({$column}, ''))) LIKE '{$pattern}'";
            }
        }
        return '(' . implode(' OR ', $conditions) . ')';
    }
}
