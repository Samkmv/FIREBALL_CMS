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
        $settings = ($this->settings ?? new SettingsService())->current();
        if ($this->expired($subscription)) {
            if (($settings['expired_subscription_behavior'] ?? 'inactive') === 'not_found') {
                return new SubscriptionEndpointResponse(404, '', $headers);
            }

            return $this->inactiveResponse($subscription, $settings, $format, $headers);
        }
        $dependencies = $this->dependencies ?? new VpnV2SubscriptionDependencyService(config: $repository);
        if ($dependencies->isDependentChild((int)$subscription['id'])) {
            return new SubscriptionEndpointResponse(403, '', $headers);
        }
        $effective = $dependencies->calculateEffectiveStatus($subscription);
        if ($effective['effective_status'] !== 'active' || !$this->started($subscription)) {
            return $this->inactiveResponse($subscription, $settings, $format, $headers);
        }

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
     * A successful update replaces previously downloaded servers with an
     * inactive placeholder. Empty bodies and HTTP errors may retain old URIs.
     */
    private function inactiveResponse(
        array $subscription,
        array $settings,
        string $format,
        array $headers
    ): SubscriptionEndpointResponse {
        $metadata = $this->metadata ?? new VpnSubscriptionMetadataService();
        // Preserve the profile identity and real expiration, but do not apply
        // routing rules from a subscription that no longer grants access.
        $inactiveHeaders = $metadata->headers($subscription, $settings);
        unset($inactiveHeaders['routing'], $inactiveHeaders['routing-enable']);
        $headers = array_replace($headers, $inactiveHeaders);
        $headers['profile-title'] = 'base64:' . base64_encode($metadata->profileTitle($subscription, $settings));
        $headers['Cache-Control'] = 'private, no-store, must-revalidate';
        $headers['X-Fireball-VPN-Status'] = 'inactive';

        $placeholder = 'vless://00000000-0000-4000-8000-000000000000@192.0.2.1:1?encryption=none&security=none&type=tcp#%E2%9B%94%20VPN-%D0%BF%D0%BE%D0%B4%D0%BF%D0%B8%D1%81%D0%BA%D0%B0%20%D0%BD%D0%B5%D0%B0%D0%BA%D1%82%D0%B8%D0%B2%D0%BD%D0%B0';
        $plain = $placeholder . "\n";
        $body = $format === 'base64' ? base64_encode($plain) : $plain;

        return new SubscriptionEndpointResponse(
            200,
            $body,
            $headers,
            1,
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
