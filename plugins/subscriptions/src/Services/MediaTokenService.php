<?php

namespace Fireball\Subscriptions\Services;

final class MediaTokenService
{
    public function issue(int $userId, string $permission, string $resourceType, string $resourceId, array $context = []): string
    {
        if (!(new AccessService())->can($userId, $permission, $context + ['camera_id' => $resourceId])) {
            throw new \RuntimeException(\FireballPluginSubscriptions::t('subscriptions_access_denied'));
        }
        $ttl = (int)(new SettingsService())->current()['media_token_ttl'];
        $payload = [
            'uid' => $userId,
            'permission' => $permission,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'context' => $this->safeContext($context),
            'iat' => time(),
            'exp' => time() + $ttl,
            'nonce' => bin2hex(random_bytes(12)),
        ];
        $encoded = $this->base64UrlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $encoded . '.' . $this->base64UrlEncode(hash_hmac('sha256', $encoded, $this->key(), true));
    }

    public function verify(string $token, int $expectedUserId, string $resourceType, string $resourceId): ?array
    {
        $payload = $this->verifiedPayload($token);
        if (!is_array($payload) || (int)($payload['uid'] ?? 0) !== $expectedUserId) {
            return null;
        }

        return $this->verifyPayloadAccess($payload, $resourceType, $resourceId);
    }

    public function verifyForResource(string $token, string $resourceType, string $resourceId): ?array
    {
        $payload = $this->verifiedPayload($token);

        return is_array($payload) ? $this->verifyPayloadAccess($payload, $resourceType, $resourceId) : null;
    }

    private function verifiedPayload(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $parts[0], $this->key(), true));
        if (!hash_equals($expected, $parts[1])) {
            return null;
        }
        $raw = $this->base64UrlDecode($parts[0]);
        $payload = $raw !== null ? json_decode($raw, true) : null;
        if (!is_array($payload) || (int)($payload['uid'] ?? 0) <= 0 || (int)($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    private function verifyPayloadAccess(array $payload, string $resourceType, string $resourceId): ?array
    {
        if (!hash_equals((string)($payload['resource_type'] ?? ''), $resourceType)
            || !hash_equals((string)($payload['resource_id'] ?? ''), $resourceId)) {
            return null;
        }
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        if (!(new AccessService())->can((int)$payload['uid'], (string)$payload['permission'], $context + ['camera_id' => $resourceId])) {
            return null;
        }

        return $payload;
    }

    private function safeContext(array $context): array
    {
        return array_intersect_key($context, array_flip(['camera_id', 'camera_group_ids', 'from', 'to', 'download', 'content_id']));
    }

    private function key(): string
    {
        $master = defined('CHAT_ENCRYPTION_KEY') ? (string)CHAT_ENCRYPTION_KEY : '';
        if ($master === '' || $master === 'change-this-chat-key-in-production') {
            throw new \RuntimeException('Configure CHAT_ENCRYPTION_KEY before issuing subscription media tokens.');
        }

        return hash_hmac('sha256', 'fireball-subscriptions-media', $master, true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
