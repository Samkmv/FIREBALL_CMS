<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ExternalSourceRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionItemRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\NetworkTargetGuard;
use Fireball\VpnManagerV2\Support\SecretCipher;

final class ExternalVpnSourceService
{
    private const MAX_SOURCE_BYTES = 2_097_152;
    private const MAX_CONFIGS = 500;
    private const URI_SCHEMES = ['vless', 'vmess', 'trojan', 'ss'];

    public function __construct(
        private readonly ?ExternalSourceRepository $repository = null,
        private readonly ?VpnSubscriptionRevisionService $revisions = null,
    ) {
    }

    public function attachSubscription(
        int $parentId,
        string $url,
        ?string $name = null,
        ?int $adminId = null
    ): int {
        $this->assertParent($parentId);
        $url = $this->subscriptionUrl($url);
        $uris = $this->extractUrisFromPayload($this->fetch($url));

        return $this->store($parentId, 'subscription_url', $url, $uris, $name, $adminId);
    }

    public function attachConnection(
        int $parentId,
        string $uri,
        ?string $name = null,
        ?int $adminId = null
    ): int {
        $this->assertParent($parentId);
        $uri = trim($uri);
        $this->validateUri($uri);

        return $this->store($parentId, 'connection_uri', $uri, [$uri], $name, $adminId);
    }

    public function itemsForParent(int $parentId): array
    {
        return $this->sources()->itemsForParent($parentId);
    }

    public function urisForParent(int $parentId): array
    {
        $uris = [];
        foreach ($this->sources()->activeUris($parentId) as $uri) {
            try {
                $this->validateUri($uri);
                $uris[] = $uri;
            } catch (\Throwable) {
                // Broken snapshot entries never reach the public subscription.
            }
        }

        return $uris;
    }

    public function configCountForParent(int $parentId): int
    {
        return $this->sources()->activeConfigCount($parentId);
    }

    public function configCountForSubscriptions(array $subscriptionIds): int
    {
        $count = 0;
        foreach (array_values(array_unique(array_filter(array_map('intval', $subscriptionIds)))) as $id) {
            $count += $this->configCountForParent($id);
        }

        return $count;
    }

    public function sync(int $parentId, int $id, ?int $adminId = null): int
    {
        $item = $this->sources()->find($parentId, $id);
        if (!$item) {
            throw new \InvalidArgumentException('external_source_missing');
        }
        try {
            $source = $this->sources()->source($item);
            $uris = (string)$item['source_type'] === 'subscription_url'
                ? $this->extractUrisFromPayload($this->fetch($source))
                : [$source];
            foreach ($uris as $uri) {
                $this->validateUri($uri);
            }
            $this->sources()->updateSnapshot($id, $uris);
            $this->touch($parentId);
            $this->log('subscription.external_source_synced', $parentId, $id, $adminId, [
                'source_type' => (string)$item['source_type'],
                'config_count' => count($uris),
            ]);

            return count($uris);
        } catch (\Throwable $exception) {
            $this->sources()->recordFailure($id, $this->safeError($exception));
            $this->touch($parentId);
            throw $exception;
        }
    }

    public function toggle(int $parentId, int $id, bool $enabled, ?int $adminId = null): bool
    {
        $item = $this->sources()->find($parentId, $id);
        if (!$item) {
            return false;
        }
        if ((bool)$item['is_enabled'] === $enabled) {
            return true;
        }
        if (!$this->sources()->setEnabled($parentId, $id, $enabled)) {
            return false;
        }
        $this->touch($parentId);
        $this->log(
            $enabled ? 'subscription.external_source_enabled' : 'subscription.external_source_disabled',
            $parentId,
            $id,
            $adminId
        );

        return true;
    }

    public function reorder(int $parentId, array $sourceIds, ?int $adminId = null): bool
    {
        $this->assertParent($parentId);
        $changed = $this->sources()->reorder($parentId, $sourceIds);
        if (!$changed) {
            return false;
        }
        $this->touch($parentId);
        $this->log('subscription.external_source_order_updated', $parentId, null, $adminId, [
            'external_source_ids' => array_values(array_map('intval', $sourceIds)),
        ]);

        return true;
    }

    public function detach(int $parentId, int $id, ?int $adminId = null): bool
    {
        if (!$this->sources()->detach($parentId, $id)) {
            return false;
        }
        $this->touch($parentId);
        $this->log('subscription.external_source_detached', $parentId, $id, $adminId);

        return true;
    }

    public function syncAll(int $limit = 20): array
    {
        $result = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'configs' => 0];
        foreach ($this->sources()->syncCandidates($limit) as $item) {
            $result['processed']++;
            try {
                $result['configs'] += $this->sync(
                    (int)$item['parent_subscription_id'],
                    (int)$item['id']
                );
                $result['synced']++;
            } catch (\Throwable) {
                $result['failed']++;
            }
        }

        return $result;
    }

    public function extractUrisFromPayload(string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '' || strlen($payload) > self::MAX_SOURCE_BYTES) {
            $this->invalidPayload();
        }
        $json = json_decode($payload, true);
        if (is_array($json)) {
            $candidate = $json['configs'] ?? $json['links'] ?? $json['subscriptions'] ?? $json;
            if (is_array($candidate)) {
                $payload = implode("\n", array_values(array_filter($candidate, 'is_string')));
            }
        }
        if (!$this->containsSupportedUri($payload)) {
            $decoded = $this->decodeBase64($payload);
            if ($decoded !== null) {
                $payload = $decoded;
            }
        }
        $unique = [];
        foreach (preg_split('/\R+/', $payload) ?: [] as $line) {
            $uri = trim($line);
            if ($uri === '' || !$this->isSupportedUri($uri)) {
                continue;
            }
            try {
                $this->validateUri($uri);
            } catch (\Throwable) {
                continue;
            }
            $unique[$this->technicalKey($uri)] = $uri;
            if (count($unique) >= self::MAX_CONFIGS) {
                break;
            }
        }
        if ($unique === []) {
            $this->invalidPayload();
        }

        return array_values($unique);
    }

    public function technicalKey(string $uri): string
    {
        $uri = trim($uri);
        if (str_starts_with(strtolower($uri), 'vmess://')) {
            $payload = $this->decodeBase64(substr($uri, 8));
            $data = $payload !== null ? json_decode($payload, true) : null;
            if (is_array($data)) {
                $identity = array_intersect_key($data, array_flip([
                    'add', 'port', 'id', 'aid', 'scy', 'net', 'type', 'host', 'path', 'tls', 'sni', 'alpn', 'fp',
                ]));
                ksort($identity);

                return hash('sha256', 'vmess|' . json_encode($identity, JSON_UNESCAPED_SLASHES));
            }
        }
        $withoutName = explode('#', $uri, 2)[0];
        $parts = parse_url($withoutName);
        if (is_array($parts) && isset($parts['scheme'])) {
            $query = [];
            parse_str((string)($parts['query'] ?? ''), $query);
            ksort($query);
            $identity = [
                strtolower((string)$parts['scheme']),
                rawurldecode((string)($parts['user'] ?? '')),
                rawurldecode((string)($parts['pass'] ?? '')),
                strtolower((string)($parts['host'] ?? '')),
                (int)($parts['port'] ?? 0),
                $query,
            ];

            return hash('sha256', json_encode($identity, JSON_UNESCAPED_SLASHES));
        }

        return hash('sha256', strtolower($withoutName));
    }

    private function store(
        int $parentId,
        string $type,
        string $source,
        array $uris,
        ?string $name,
        ?int $adminId
    ): int {
        $name = trim((string)$name);
        if ($name === '') {
            $name = \FireballPluginVpnManagerV2::t($type === 'subscription_url'
                ? 'vpn_manager_v2_external_subscription_default_name'
                : 'vpn_manager_v2_external_connection_default_name');
        }
        if (mb_strlen($name) > 160) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_name'));
        }
        $json = json_encode(array_values($uris), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $sourceHash = hash('sha256', $type . "\0" . $source);
        $id = $this->sources()->create(
            $parentId,
            $type,
            $name,
            SecretCipher::encrypt($source),
            $sourceHash,
            $this->preview($type, $source),
            SecretCipher::encrypt($json),
            hash('sha256', $json),
            count($uris),
            $adminId
        );
        $this->touch($parentId);
        $this->log('subscription.external_source_attached', $parentId, $id, $adminId, [
            'source_type' => $type,
            'config_count' => count($uris),
        ]);

        return $id;
    }

    private function subscriptionUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (strlen($url) > 2048 || !is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host']) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_url'));
        }

        return $url;
    }

    private function fetch(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_curl_required'));
        }
        $addresses = (new NetworkTargetGuard())->validatedRequestAddresses($url, false);
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'FIREBALL-CMS-VPN-Manager-V2/0.19.5',
            CURLOPT_HTTPHEADER => ['Accept: text/plain, application/json;q=0.9, */*;q=0.5'],
        ]);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        $resolve = $this->curlResolveEntries($url, $addresses);
        if ($resolve !== []) {
            curl_setopt($handle, CURLOPT_RESOLVE, $resolve);
        }
        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($handle);
        curl_close($handle);
        if (!is_string($body) || $error !== 0 || $status < 200 || $status >= 300
            || strlen($body) > self::MAX_SOURCE_BYTES) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_fetch'));
        }

        return $body;
    }

    private function validateUri(string $uri): void
    {
        if (!$this->isSupportedUri($uri) || strlen($uri) > 16_384
            || str_contains($uri, "\n") || str_contains($uri, "\r")) {
            $this->invalidPayload();
        }
        if (str_starts_with(strtolower($uri), 'ss://')) {
            $payload = explode('#', substr($uri, 5), 2)[0];
            $decoded = null;
            if (str_contains($payload, '@')) {
                [$credentials, $target] = explode('@', $payload, 2);
                $credentials = rawurldecode($credentials);
                if (!str_contains($credentials, ':')) {
                    $credentials = $this->decodeBase64($credentials) ?? '';
                }
                $decoded = $credentials . '@' . $target;
            } else {
                $decoded = $this->decodeBase64($payload);
            }
            if (!is_string($decoded) || !str_contains($decoded, '@')
                || preg_match('/^.+:.+@(?:\[[0-9a-f:]+\]|[^:@\/]+):(\d{1,5})$/i', $decoded, $matches) !== 1
                || (int)$matches[1] < 1 || (int)$matches[1] > 65535
                || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
                $this->invalidPayload();
            }

            return;
        }
        (new VpnConfigValidator())->validateUri($uri);
    }

    private function isSupportedUri(string $uri): bool
    {
        $scheme = strtolower((string)(parse_url(trim($uri), PHP_URL_SCHEME) ?: ''));

        return in_array($scheme, self::URI_SCHEMES, true);
    }

    private function containsSupportedUri(string $payload): bool
    {
        foreach (self::URI_SCHEMES as $scheme) {
            if (stripos($payload, $scheme . '://') !== false) {
                return true;
            }
        }

        return false;
    }

    private function decodeBase64(string $value): ?string
    {
        $value = preg_replace('/\s+/', '', trim($value)) ?? '';
        if ($value === '') {
            return null;
        }
        $value = strtr($value, '-_', '+/');
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($value, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    private function preview(string $type, string $source): string
    {
        if ($type === 'subscription_url') {
            $parts = parse_url($source);
            $scheme = strtolower((string)($parts['scheme'] ?? 'https'));
            $host = (string)($parts['host'] ?? '');
            $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';

            return mb_substr($scheme . '://' . $host . $port . '/…', 0, 255);
        }
        $scheme = strtolower((string)(parse_url($source, PHP_URL_SCHEME) ?: 'vpn'));
        $withoutName = explode('#', $source, 2)[0];
        $parts = parse_url($withoutName);
        if (str_contains($withoutName, '@') && is_array($parts) && !empty($parts['host'])) {
            return mb_substr($scheme . '://••••@' . $parts['host']
                . (isset($parts['port']) ? ':' . (int)$parts['port'] : ''), 0, 255);
        }

        return $scheme . '://••••';
    }

    private function curlResolveEntries(string $url, array $addresses): array
    {
        $host = trim((string)(parse_url($url, PHP_URL_HOST) ?: ''), '[]');
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: 'https'));
        $port = (int)(parse_url($url, PHP_URL_PORT) ?: ($scheme === 'http' ? 80 : 443));

        return array_values(array_map(
            static fn(string $address): string => $host . ':' . $port . ':'
                . (str_contains($address, ':') ? '[' . $address . ']' : $address),
            $addresses
        ));
    }

    private function assertParent(int $parentId): void
    {
        $parent = (new SubscriptionItemRepository())->subscription($parentId);
        if (!$parent || in_array((string)$parent['status'], ['deleting', 'deleted'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }
    }

    private function touch(int $parentId): void
    {
        ($this->revisions ?? new VpnSubscriptionRevisionService())->touchConfig($parentId);
    }

    private function log(
        string $event,
        int $parentId,
        ?int $externalId,
        ?int $adminId,
        array $context = []
    ): void {
        $parent = (new SubscriptionItemRepository())->subscription($parentId);
        (new SubscriptionRepository())->logEvent(
            $event,
            $parentId,
            null,
            null,
            $parent ? (int)$parent['user_id'] : null,
            $adminId,
            ($externalId !== null ? ['external_source_id' => $externalId] : []) + $context
        );
    }

    private function safeError(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return mb_substr($message !== '' ? $message
            : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_fetch'), 0, 1000);
    }

    private function invalidPayload(): never
    {
        throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_payload'));
    }

    private function sources(): ExternalSourceRepository
    {
        return $this->repository ?? new ExternalSourceRepository();
    }
}
