<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\SubscriptionEndpointResponse;
use Fireball\VpnManagerV2\Exceptions\VpnManagerV2Exception;
use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;

final class VpnSubscriptionEndpointService
{
    public function __construct(
        private readonly ?SubscriptionConfigRepository $repository = null,
        private readonly ?VpnSubscriptionBuilder $builder = null,
        private readonly ?VpnSubscriptionCache $subscriptionCache = null,
        private readonly ?SettingsService $settings = null,
        private readonly ?VpnV2SubscriptionDependencyService $dependencies = null,
        private readonly ?VpnSubscriptionMetadataService $metadata = null,
    ) {
    }

    public function respond(
        string $token,
        string $format = 'base64',
        string $ifNoneMatch = '',
        string $ifModifiedSince = ''
    ): SubscriptionEndpointResponse {
        $headers = $this->baseHeaders();
        $token = strtolower(trim($token));
        $format = strtolower(trim($format));
        if (!in_array($format, ['base64', 'plain'], true)) {
            return new SubscriptionEndpointResponse(400, '', $headers);
        }
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return new SubscriptionEndpointResponse(404, '', $headers);
        }

        $repository = $this->repository ?? new SubscriptionConfigRepository();
        $subscription = $repository->findByToken($token);
        if (!$subscription) {
            return new SubscriptionEndpointResponse(404, '', $headers);
        }
        if ($this->expired($subscription)) {
            // FIREBALL_VPN_EXTERNAL_INACTIVE_CLEANUP_V1
            // Если эта подписка отдавала активные внешние источники,
            // HTTP 404/410 не подходит: VPN-клиент может оставить старые URI.
            // Успешный пустой payload позволяет заменить старый набор на пустой.
            if ($this->hasActiveExternalSources($subscription)) {
                return $this->inactiveExternalCleanupResponse($format, $headers);
            }

            $settings = ($this->settings ?? new SettingsService())->current();
            $status = (string)($settings['expired_subscription_behavior'] ?? 'gone') === 'not_found' ? 404 : 410;

            return new SubscriptionEndpointResponse($status, '', $headers);
        }
        $dependencies = $this->dependencies ?? new VpnV2SubscriptionDependencyService(config: $repository);
        if ($dependencies->isDependentChild((int)$subscription['id'])) {
            return new SubscriptionEndpointResponse(403, '', $headers);
        }
        $effective = $dependencies->calculateEffectiveStatus($subscription);
        if ($effective['effective_status'] !== 'active' || !$this->started($subscription)) {
            if ($this->hasActiveExternalSources($subscription)) {
                return $this->inactiveExternalCleanupResponse($format, $headers);
            }

            return new SubscriptionEndpointResponse(403, '', $headers);
        }

        $settings = ($this->settings ?? new SettingsService())->current();
        $metadata = $this->metadata ?? new VpnSubscriptionMetadataService();
        $subscriptionName = $metadata->profileTitle($subscription, $settings);
        if ($subscriptionName !== '') {
            $headers['profile-title'] = 'base64:' . base64_encode($subscriptionName);
        }
        $headers = array_replace(
            $headers,
            $metadata->headers($subscription, $settings, $repository->trafficBreakdown((int)$subscription['id']))
        );
        $revision = max(1, (int)$subscription['revision']);
        $etag = $this->etag($token, $revision, $format);
        $modifiedTimestamp = $this->modifiedTimestamp($subscription);
        $headers['ETag'] = $etag;
        $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $modifiedTimestamp) . ' GMT';
        if ($this->etagMatches($ifNoneMatch, $etag)
            || (trim($ifNoneMatch) === '' && $this->notModifiedSince($ifModifiedSince, $modifiedTimestamp))) {
            return new SubscriptionEndpointResponse(304, '', $headers);
        }

        $cache = $this->subscriptionCache ?? new VpnSubscriptionCache();
        $cached = $cache->get($token, $revision, $format);
        if ($cached !== null) {
            return new SubscriptionEndpointResponse(
                200,
                (string)$cached['body'],
                $headers,
                (int)$cached['config_count'],
                true
            );
        }

        try {
            $uris = ($this->builder ?? new VpnSubscriptionBuilder(
                repository: $repository,
                dependencies: $dependencies
            ))->build($subscription);
        } catch (VpnManagerV2Exception) {
            return new SubscriptionEndpointResponse(422, '', $headers);
        }
        if ($uris === []) {
            return new SubscriptionEndpointResponse(422, '', $headers);
        }

        $plain = implode("\n", $uris) . "\n";
        $body = $format === 'base64' ? base64_encode($plain) : $plain;
        $cache->set($token, $revision, $format, $body, count($uris));

        return new SubscriptionEndpointResponse(200, $body, $headers, count($uris), false);
    }

    public function etag(string $token, int $revision, string $format): string
    {
        return '"vpn-v2-' . hash('sha256', hash('sha256', $token) . '|' . max(1, $revision) . '|' . $format) . '"';
    }

    private function baseHeaders(): array
    {
        return [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function started(array $subscription): bool
    {
        $timestamp = strtotime((string)($subscription['starts_at'] ?? ''));

        return $timestamp === false || $timestamp <= time();
    }

    private function expired(array $subscription): bool
    {
        $expiresAt = trim((string)($subscription['expires_at'] ?? ''));
        if ($expiresAt === '') {
            return false;
        }
        $timestamp = strtotime($expiresAt);

        return $timestamp !== false && $timestamp <= time();
    }

    /**
     * Проверяет активные внешние источники не только у корневой подписки,
     * но и у включённых дочерних subscription-зависимостей.
     *
     * Проверка дерева намеренно не зависит от effective_status родителя:
     * этот метод вызывается как раз тогда, когда родитель уже expired/suspended.
     */
    private function hasActiveExternalSources(array $subscription): bool
    {
        $rootId = (int)($subscription['id'] ?? 0);
        if ($rootId <= 0) {
            return false;
        }

        $external = new ExternalVpnSourceService();
        $items = new \Fireball\VpnManagerV2\Repositories\SubscriptionItemRepository();
        $stack = [$rootId];
        $visited = [];

        while ($stack !== []) {
            $subscriptionId = (int)array_pop($stack);
            if ($subscriptionId <= 0 || isset($visited[$subscriptionId])) {
                continue;
            }
            $visited[$subscriptionId] = true;

            if ($external->configCountForParent($subscriptionId) > 0) {
                return true;
            }

            foreach ($items->itemsForParent($subscriptionId, true) as $item) {
                if ((string)($item['item_type'] ?? '') !== 'subscription') {
                    continue;
                }

                $childId = (int)($item['child_subscription_id'] ?? 0);
                if ($childId > 0 && !isset($visited[$childId])) {
                    $stack[] = $childId;
                }
            }
        }

        return false;
    }

    /**
     * Возвращает синтаксически корректное, но пустое обновление подписки.
     *
     * Для base64 это "Cg==" (одна пустая строка после декодирования),
     * для plain — перевод строки. В payload нет ни одного VPN URI.
     */
    private function inactiveExternalCleanupResponse(
        string $format,
        array $headers
    ): SubscriptionEndpointResponse {
        $headers['Cache-Control'] = 'private, no-store, must-revalidate';
        $headers['X-Fireball-VPN-Status'] = 'inactive';

        $plain = "\n";
        $body = $format === 'base64' ? base64_encode($plain) : $plain;

        return new SubscriptionEndpointResponse(
            200,
            $body,
            $headers,
            0,
            false
        );
    }

    private function modifiedTimestamp(array $subscription): int
    {
        foreach (['config_updated_at', 'updated_at', 'created_at'] as $key) {
            $timestamp = strtotime((string)($subscription[$key] ?? ''));
            if ($timestamp !== false && $timestamp > 0) {
                return $timestamp;
            }
        }

        return 1;
    }

    private function etagMatches(string $header, string $etag): bool
    {
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*') {
                return true;
            }
            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }
            if ($candidate !== '' && hash_equals($etag, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function notModifiedSince(string $header, int $modifiedTimestamp): bool
    {
        if (trim($header) === '') {
            return false;
        }
        $timestamp = strtotime($header);

        // Equality is intentionally not a 304: DATETIME has one-second precision and two
        // different configurations may be confirmed within the same second. ETag remains exact.
        return $timestamp !== false && $timestamp > $modifiedTimestamp;
    }
}
