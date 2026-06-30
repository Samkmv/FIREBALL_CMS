<?php

namespace App\Services;

class NotificationService
{
    public static function send(array $payload, array $options = []): array
    {
        return (new PwaService())->send($payload, $options);
    }

    public static function sendToUser(int $userId, array $payload): array
    {
        return self::send($payload, ['user_id' => $userId]);
    }

    public static function sendToUsers(array $userIds, array $payload): array
    {
        return self::send($payload, ['user_ids' => $userIds]);
    }

    public static function broadcast(array $payload): array
    {
        return self::send($payload);
    }
}
