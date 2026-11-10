<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\ExternalSourceRepository;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionItemRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Support\BoundedResponseBody;
use Fireball\VpnManagerV2\Support\NetworkTargetGuard;
use Fireball\VpnManagerV2\Support\SecretCipher;
use Fireball\VpnManagerV2\Support\VpnReconciliationLock;

final class ExternalVpnSourceService
{
    private const MAX_SOURCE_BYTES = 2_097_152;
    private const MAX_CONFIGS = 500;
    private const URI_SCHEMES = ['vless', 'vmess', 'trojan', 'ss'];

    public function __construct(
        private readonly ?ExternalSourceRepository $repository = null,
        private readonly ?VpnSubscriptionRevisionService $revisions = null,
        private readonly bool $forPlan = false,
        private readonly ?\Closure $fetcher = null,
        private readonly int $fetchTimeout = 30,
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

    /** Inherited sources are read-only here; CRUD must use the plan's own scope. */
    public function planItemsForParent(int $parentId): array
    {
        $planId = $this->sources()->planIdForSubscription($parentId);
        return $planId > 0 ? (new ExternalSourceRepository(forPlan: true))->itemsForParent($planId) : [];
    }

    public function urisForParent(int $parentId): array
    {
        $uris = [];
        $snapshots = $this->sources()->activeUris($parentId);
        if (!$this->forPlan) {
            $planId = $this->sources()->planIdForSubscription($parentId);
            if ($planId > 0) {
                array_push($snapshots, ...(new ExternalSourceRepository(forPlan: true))->activeUris($planId));
            }
        }
        foreach ($snapshots as $uri) {
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
        return $this->configCountForSubscriptions([$parentId]);
    }

    public function configCountForSubscriptions(array $subscriptionIds): int
    {
        $count = 0;
        $plans = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $subscriptionIds)))) as $id) {
            $count += $this->sources()->activeConfigCount($id);
            if (!$this->forPlan) {
                $planId = $this->sources()->planIdForSubscription($id);
                if ($planId > 0) { $plans[$planId] = true; }
            }
        }
        foreach (array_keys($plans) as $planId) {
            $count += (new ExternalSourceRepository(forPlan: true))->activeConfigCount($planId);
        }

        return $count;
    }

    public function sync(int $parentId, int $id, ?int $adminId = null, bool $onlyDue = false): int
    {
        return (new VpnReconciliationLock())->externalSource(
            $id,
            fn(): int => $this->refreshSnapshot($parentId, $id, $adminId, $onlyDue)
        );
    }

    private function refreshSnapshot(int $parentId, int $id, ?int $adminId, bool $onlyDue): int
    {
        $item = $this->sources()->find($parentId, $id);
        if (!$item) {
            throw new \InvalidArgumentException('external_source_missing');
        }
        if ($onlyDue && (empty($item['is_enabled'])
            || strtotime((string)$item['updated_at']) > time() - ExternalSourceRepository::REFRESH_INTERVAL_SECONDS)) {
            return 0;
        }
        try {
            $source = $this->sources()->source($item);
            $uris = (string)$item['source_type'] === 'subscription_url'
                ? $this->extractUrisFromPayload($this->fetch($this->subscriptionUrl($source)))
                : [$source];
            foreach ($uris as $uri) {
                $this->validateUri($uri);
            }
            $database = db();
            $database->beginTransaction();
            try {
                $current = $this->sources()->find($parentId, $id, true);
                if (!$current) {
                    throw new \InvalidArgumentException('external_source_missing');
                }
                $this->sources()->updateSnapshot($id, $uris);
                $hash = hash('sha256', json_encode(array_values($uris), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                if ($hash !== (string)$current['snapshot_hash']) {
                    $this->touch($parentId);
                }
                $database->commit();
            } catch (\Throwable $exception) {
                $database->rollBack();
                throw $exception;
            }
        } catch (\Throwable $exception) {
            $this->sources()->recordFailure($id, $this->safeError($exception));
            throw $exception;
        }
        $this->log('subscription.external_source_synced', $parentId, $id, $adminId, [
            'source_type' => (string)$item['source_type'],
            'config_count' => count($uris),
        ]);

        return count($uris);
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

    /** Refresh each inherited plan once, including plans of linked subscriptions. */
    public function syncForSubscriptions(array $subscriptionIds, ?int $adminId = null): array
    {
        $owners = [];
        foreach ($subscriptionIds as $subscriptionId) {
            $owners['subscriptions'][(int)$subscriptionId] = true;
            $planId = $this->sources()->planIdForSubscription((int)$subscriptionId);
            if ($planId > 0) {
                $owners['plans'][$planId] = true;
            }
        }

        $result = ['synced' => 0, 'failed' => 0];
        foreach ($owners as $scope => $ids) {
            $service = $scope === 'subscriptions' ? $this
                : new self(revisions: $this->revisions, forPlan: true, fetcher: $this->fetcher);
            foreach (array_keys($ids) as $id) {
                foreach ($service->itemsForParent($id) as $item) {
                    if (empty($item['is_enabled']) || $item['source_type'] !== 'subscription_url') {
                        continue;
                    }
                    try {
                        $service->sync($id, (int)$item['id'], $adminId);
                        $result['synced']++;
                    } catch (\Throwable) {
                        $result['failed']++;
                    }
                }
            }
        }

        return $result;
    }

    public function syncAll(int $limit = 20, ?array $subscriptionIds = null): array
    {
        $result = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'configs' => 0];
        foreach ($this->sources()->syncCandidates($limit, $subscriptionIds) as $item) {
            $result['processed']++;
            try {
                $planId = (int)($item['plan_id'] ?? 0);
                $forPlan = $planId > 0;
                $service = $forPlan === $this->forPlan ? $this
                    : new self(revisions: $this->revisions, forPlan: $forPlan, fetcher: $this->fetcher,
                        fetchTimeout: $this->fetchTimeout);
                $count = $service->sync(
                    $planId > 0 ? $planId : (int)$item['parent_subscription_id'],
                    (int)$item['id'],
                    onlyDue: true
                );
                if ($count > 0) {
                    $result['configs'] += $count;
                    $result['synced']++;
                }
            } catch (\Throwable) {
                $result['failed']++;
            }
        }

        return $result;
    }

    public function extractUrisFromPayload(string $payload): array
    {
        if (str_starts_with($payload, "\xEF\xBB\xBF")) {
            $payload = substr($payload, 3);
        }
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
        if (is_array($parts) && strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && strtolower((string)($parts['host'] ?? '')) === 'translate.yandex.ru'
            && ($parts['path'] ?? '') === '/translate' && strlen($url) <= 8192) {
            parse_str((string)($parts['query'] ?? ''), $query);
            $url = is_string($query['url'] ?? null) ? trim($query['url']) : '';
            $parts = parse_url($url);
        }
        if (strlen($url) > 2048 || !is_array($parts)
            || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_url'));
        }

        return $url;
    }

    private function fetch(string $url): string
    {
        // Trusted dependency injection for offline tests; requests never supply this callback.
        if ($this->fetcher !== null) {
            $body = ($this->fetcher)($url);
            if (!is_string($body) || strlen($body) > self::MAX_SOURCE_BYTES) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_fetch'));
            }
            return $body;
        }
        if (!function_exists('curl_init')) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_curl_required'));
        }
        $guard = new NetworkTargetGuard();
        $addresses = $guard->validatedRequestAddresses($url, false);
        $response = new BoundedResponseBody(self::MAX_SOURCE_BYTES);
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_WRITEFUNCTION => $response->append(...),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT => min(10, $this->fetchTimeout),
            CURLOPT_TIMEOUT => $this->fetchTimeout,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'FIREBALL-CMS-VPN-Manager/3.0.0',
            CURLOPT_HTTPHEADER => ['Accept: text/plain, application/json;q=0.9, */*;q=0.5'],
        ]);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        $resolve = $guard->curlResolveEntries($url, $addresses);
        if ($resolve !== []) {
            curl_setopt($handle, CURLOPT_RESOLVE, $resolve);
        }
        $success = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($handle);
        curl_close($handle);
        if ($success === false || $error !== 0 || $status < 200 || $status >= 300) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_external_fetch'));
        }

        return $response->body;
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

    private function assertParent(int $parentId): void
    {
        if ($this->forPlan) {
            if (!(new PlanRepository())->find($parentId)) {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_not_found'));
            }
            return;
        }
        $parent = (new SubscriptionItemRepository())->subscription($parentId);
        if (!$parent || in_array((string)$parent['status'], ['deleting', 'deleted'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_subscription_not_found'));
        }
    }

    private function touch(int $parentId): void
    {
        $revisions = $this->revisions ?? new VpnSubscriptionRevisionService();
        if ($this->forPlan) { $revisions->touchByPlan($parentId); }
        else { $revisions->touchConfig($parentId); }
    }

    private function log(
        string $event,
        int $parentId,
        ?int $externalId,
        ?int $adminId,
        array $context = []
    ): void {
        $parent = $this->forPlan ? null : (new SubscriptionItemRepository())->subscription($parentId);
        (new SubscriptionRepository())->logEvent(
            $this->forPlan ? str_replace('subscription.', 'plan.', $event) : $event,
            $this->forPlan ? null : $parentId,
            null,
            null,
            $parent ? (int)$parent['user_id'] : null,
            $adminId,
            ($this->forPlan ? ['plan_id' => $parentId] : [])
                + ($externalId !== null ? ['external_source_id' => $externalId] : []) + $context
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
        return $this->repository ?? new ExternalSourceRepository(forPlan: $this->forPlan);
    }
}
