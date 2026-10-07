<?php

namespace Fireball\VpnManagerV2\Clients;

use Fireball\VpnManagerV2\DTO\ConnectionTestResult;
use Fireball\VpnManagerV2\DTO\ThreeXuiHttpResponse;
use Fireball\VpnManagerV2\DTO\ThreeXuiServerConfig;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiAuthenticationException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiHttpException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Support\NetworkTargetGuard;

final class ThreeXuiClient implements ThreeXuiClientInterface
{
    private const MAX_RESPONSE_BYTES = 8388608;

    private ?string $cookieFile = null;
    private ?string $csrfToken = null;
    private bool $authenticated = false;
    private ?array $cachedInbounds = null;
    private ?string $cachedSubscriptionBaseUrl = null;

    public function __construct(
        private readonly ThreeXuiServerConfig $config,
        private readonly ?\Closure $transport = null,
    ) {
    }

    public function __destruct()
    {
        if ($this->cookieFile !== null && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function authenticate(): void
    {
        if ($this->authenticated) {
            return;
        }

        if ($this->config->authType === 'token') {
            if ($this->config->token === '') {
                throw new ThreeXuiAuthenticationException($this->message('vpn_manager_v2_error_token_required'));
            }

            try {
                $this->cachedInbounds = $this->fetchInbounds();
                $this->authenticated = true;
            } catch (\Throwable $exception) {
                $this->authenticated = false;
                throw $exception;
            }

            return;
        }

        if ($this->config->username === '' || $this->config->password === '') {
            throw new ThreeXuiAuthenticationException($this->message('vpn_manager_v2_error_credentials_required'));
        }

        $this->ensureCookieFile();
        $this->prepareSessionCsrfToken();
        $response = $this->request('POST', $this->config->endpoint('/login'), [
            'username' => $this->config->username,
            'password' => $this->config->password,
            'twoFactorCode' => '',
        ]);
        $decoded = $this->decodeJsonResponse($response, true);
        if (($decoded['success'] ?? null) !== true) {
            throw new ThreeXuiAuthenticationException($this->message('vpn_manager_v2_error_authentication_failed'));
        }

        $this->authenticated = true;
    }

    public function listClientDevices(string $email): array
    {
        $this->authenticate();
        $response = $this->requestJson('POST', $this->config->endpoint('/panel/api/clients/hwids/' . rawurlencode($email)));
        $rows = $response['obj'] ?? [];
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_devices_response'));
        }
        return array_values(array_filter($rows, 'is_array'));
    }

    public function deleteClientDevice(string $email, int $deviceId): void
    {
        if ($deviceId <= 0) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_remote_id'));
        }
        $this->authenticate();
        $this->requestJson('DELETE', $this->config->endpoint('/panel/api/clients/hwids/' . rawurlencode($email) . '/' . $deviceId));
    }

    public function clearClientDevices(string $email): void
    {
        $this->authenticate();
        $this->requestJson('DELETE', $this->config->endpoint('/panel/api/clients/hwids/' . rawurlencode($email)));
    }

    /**
     * FIREBALL_VPN_HWID_GATEWAY_V1
     *
     * Ask the native 3x-ui subscription server to perform its own HWID gate.
     * This request deliberately carries no panel Authorization/Cookie/CSRF data.
     */
    public function probeSubscriptionHwid(string $subId, array $deviceHeaders): int
    {
        $subId = trim($subId);
        if ($subId === '' || strlen($subId) > 255) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_client_response'));
        }

        $url = rtrim($this->subscriptionBaseUrl(), '/') . '/' . rawurlencode($subId);

        return $this->publicSubscriptionHead($url, $deviceHeaders);
    }

    public function testConnection(): ConnectionTestResult
    {
        $this->authenticate();
        $inbounds = $this->listInbounds();

        return new ConnectionTestResult(
            success: true,
            message: sprintf($this->message('vpn_manager_v2_connection_success'), count($inbounds)),
            inboundCount: count($inbounds),
            status: 'online',
        );
    }

    public function serverStatus(): array
    {
        $this->authenticate();
        $decoded = $this->requestJson('GET', $this->config->endpoint('/panel/api/server/status'));
        $status = $decoded['obj'] ?? $decoded;
        if (!is_array($status) || array_is_list($status)) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_server_metrics_response'));
        }

        return $status;
    }

    public function listInbounds(): array
    {
        $this->authenticate();
        if ($this->cachedInbounds !== null) {
            return $this->cachedInbounds;
        }

        return $this->cachedInbounds = $this->fetchInbounds();
    }

    public function getInbound(int $remoteInboundId): array
    {
        if ($remoteInboundId <= 0) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_remote_id'));
        }

        $this->authenticate();
        $decoded = $this->requestJson('GET', $this->config->endpoint('/panel/api/inbounds/get/' . $remoteInboundId));

        return (new ThreeXuiResponseMapper())->inbound($decoded);
    }

    public function getClientTraffic(string $clientIdentifier): array
    {
        $clientIdentifier = trim($clientIdentifier);
        if ($clientIdentifier === '') {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_client_traffic_identifier'));
        }

        $this->authenticate();
        try {
            return $this->requestJson(
                'GET',
                $this->config->endpoint('/panel/api/clients/traffic/' . rawurlencode($clientIdentifier))
            );
        } catch (ThreeXuiHttpException $exception) {
            if (!$this->isEndpointFallbackStatus($exception)) {
                throw $exception;
            }
        }

        return $this->requestJson(
            'GET',
            $this->config->endpoint('/panel/api/inbounds/getClientTraffics/' . rawurlencode($clientIdentifier))
        );
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array
    {
        $inbound = $this->getInbound($remoteInboundId);
        foreach ((new ThreeXuiResponseMapper())->clients($inbound) as $client) {
            if (!is_array($client)) {
                continue;
            }

            $email = (string)($client['email'] ?? '');
            if (($clientId !== '' && $this->clientCredentialMatches($client, $clientId))
                || ($clientEmail !== '' && hash_equals($email, $clientEmail))) {
                return $client;
            }
        }

        return null;
    }

    /**
     * FIREBALL_VPN_REPAIR_V2: global-client-lookup
     *
     * Modern 3x-ui stores clients as first-class records identified by email.
     * A client may exist globally without being attached to this inbound.
     *
     * This method deliberately is not added to ThreeXuiClientInterface:
     * older test doubles / legacy implementations remain compatible and callers
     * feature-detect it through method_exists().
     *
     * @return array{client: array, inbound_ids: array<int, int>}|null
     */
    public function findGlobalClient(string $clientEmail): ?array
    {
        $clientEmail = trim($clientEmail);
        if ($clientEmail === '') {
            return null;
        }

        $this->authenticate();

        try {
            $decoded = $this->requestJson(
                'GET',
                $this->config->endpoint('/panel/api/clients/get/' . rawurlencode($clientEmail))
            );
        } catch (ThreeXuiHttpException $exception) {
            // 404/405 also covers old 3x-ui releases without the modern client API.
            if ($this->isEndpointFallbackStatus($exception)) {
                return null;
            }
            throw $exception;
        } catch (ThreeXuiResponseException $exception) {
            // Current 3x-ui may report a missing client as success:false + msg.
            $message = mb_strtolower(trim($exception->getMessage()));
            foreach ([
                'client not found',
                'not found',
                'does not exist',
                'не найден',
                'nicht gefunden',
                '未找到',
                '不存在',
            ] as $needle) {
                if (str_contains($message, $needle)) {
                    return null;
                }
            }
            throw $exception;
        }

        $obj = $decoded['obj'] ?? null;
        if (!is_array($obj)) {
            return null;
        }

        $client = is_array($obj['client'] ?? null) ? $obj['client'] : $obj;
        if (!is_array($client) || array_is_list($client)) {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_invalid_client_response')
            );
        }

        // In the global-client response `id` may be a DB row id while `uuid`
        // is the VLESS/VMess credential. ClientVerifier expects `id` to be the
        // protocol credential just like an inbound client record.
        $uuid = trim((string)($client['uuid'] ?? ''));
        if ($uuid !== '') {
            $client['id'] = $uuid;
        }

        $ids = $obj['inboundIds']
            ?? $obj['inbound_ids']
            ?? $client['inboundIds']
            ?? $client['inbound_ids']
            ?? [];
        $inboundIds = is_array($ids)
            ? array_values(array_unique(array_filter(
                array_map('intval', $ids),
                static fn(int $id): bool => $id > 0
            )))
            : [];

        return [
            'client' => $client,
            'inbound_ids' => $inboundIds,
        ];
    }

    /**
     * Attach an existing modern 3x-ui client to another inbound without
     * recreating or changing its stable credential.
     */
    public function attachGlobalClient(string $clientEmail, int $remoteInboundId): array
    {
        $clientEmail = trim($clientEmail);
        if ($clientEmail === '' || $remoteInboundId <= 0) {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_invalid_client_response')
            );
        }

        $this->authenticate();

        return $this->requestJson(
            'POST',
            $this->config->endpoint(
                '/panel/api/clients/' . rawurlencode($clientEmail) . '/attach'
            ),
            ['inboundIds' => [$remoteInboundId]],
            'json'
        );
    }

    public function addClient(int $remoteInboundId, array $client): array
    {
        if ($remoteInboundId <= 0) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_remote_id'));
        }
        $this->authenticate();

        try {
            return $this->requestJson('POST', $this->config->endpoint('/panel/api/clients/add'), [
                'client' => $client,
                'inboundIds' => [$remoteInboundId],
            ], 'json');
        } catch (ThreeXuiHttpException $exception) {
            if (!$this->isEndpointFallbackStatus($exception)) {
                throw $exception;
            }
        }

        return $this->requestJson('POST', $this->config->endpoint('/panel/api/inbounds/addClient'), [
            'id' => $remoteInboundId,
            'settings' => $this->encodeJson(['clients' => [$client]]),
        ]);
    }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array
    {
        return $this->updateClientAtEmail($remoteInboundId, $clientId, (string)($client['email'] ?? ''), $client);
    }

    /** The modern API addresses the existing name, even when the payload renames it. */
    public function updateClientAtEmail(int $remoteInboundId, string $clientId, string $currentEmail, array $client): array
    {
        $this->authenticate();
        $email = trim($currentEmail);

        if ($email !== '') {
            try {
                // The current API replaces a complete client record. Inbound
                // snapshots omit global fields such as limitHwid; preserve them
                // when changing expiry/enable or other locally managed values.
                $details = $this->requestJson(
                    'GET',
                    $this->config->endpoint('/panel/api/clients/get/' . rawurlencode($email))
                );
                $record = $details['obj']['client'] ?? null;
                if (!is_array($record) || array_is_list($record)
                    || (string)($record['email'] ?? '') !== $email) {
                    throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_client_response'));
                }
                // A label can be reused or changed between reads. Never update
                // a different client, or rotate the original protocol credential.
                if (!$this->clientCredentialMatches($record, $clientId)
                    || !$this->clientCredentialMatches($client, $clientId)) {
                    throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_client_identity_changed'));
                }
                // FIREBALL_VPN_REPAIR_V3: normalize-modern-client
                // Modern 3x-ui can return list fields in display-friendly string form.
                // The update DTO expects typed JSON arrays.
                $modernPayload = $this->normalizeModernClientPayload(
                    array_replace($record, $client)
                );

                return $this->requestJson(
                    'POST',
                    $this->config->endpoint('/panel/api/clients/update/' . rawurlencode($email))
                        . '?inboundIds=' . rawurlencode((string)$remoteInboundId),
                    $modernPayload,
                    'json'
                );
            } catch (ThreeXuiHttpException $exception) {
                if (!$this->isEndpointFallbackStatus($exception)) {
                    throw $exception;
                }
            }
        }

        return $this->requestJson('POST', $this->config->endpoint('/panel/api/inbounds/updateClient/' . rawurlencode($clientId)), [
            'id' => $remoteInboundId,
            'settings' => $this->encodeJson(['clients' => [$client]]),
        ]);
    }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array
    {
        $this->authenticate();
        $email = trim((string)$clientEmail);

        if ($email !== '') {
            try {
                $inboundIds = $this->modernClientInboundIds($email);
            } catch (ThreeXuiHttpException $exception) {
                if (!$this->isEndpointFallbackStatus($exception)) {
                    throw $exception;
                }
                $inboundIds = $this->clientInboundIdsFromList($clientId, $email);
            }
            if ($inboundIds === []) {
                // The caller has already confirmed the client in this inbound.
                // Treat an empty attachment list as a stale projection, never
                // as permission to delete an unrelated global client.
                $inboundIds = [$remoteInboundId];
            }

            try {
                if (count(array_unique($inboundIds)) > 1) {
                    return $this->requestJson(
                        'POST',
                        $this->config->endpoint('/panel/api/clients/' . rawurlencode($email) . '/detach'),
                        ['inboundIds' => [$remoteInboundId]],
                        'json'
                    );
                }

                return $this->requestJson(
                    'POST',
                    $this->config->endpoint('/panel/api/clients/del/' . rawurlencode($email)) . '?keepTraffic=0',
                    [],
                    'json'
                );
            } catch (ThreeXuiHttpException $exception) {
                if (!$this->isEndpointFallbackStatus($exception)) {
                    throw $exception;
                }
            }
        }

        return $this->requestJson(
            'POST',
            $this->config->endpoint('/panel/api/inbounds/' . $remoteInboundId . '/delClient/' . rawurlencode($clientId))
        );
    }

    /** Explicit command only; ordinary synchronization never calls this method. */
    public function resetClientTraffic(int $remoteInboundId, string $clientEmail): array
    {
        $clientEmail = trim($clientEmail);
        if ($remoteInboundId <= 0 || $clientEmail === '') {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_client_traffic_identifier'));
        }
        $this->authenticate();

        try {
            return $this->requestJson(
                'POST',
                $this->config->endpoint('/panel/api/clients/resetTraffic/' . rawurlencode($clientEmail)),
                [],
                'json'
            );
        } catch (ThreeXuiHttpException $exception) {
            if (!$this->isEndpointFallbackStatus($exception)) {
                throw $exception;
            }
        }

        return $this->requestJson('POST', $this->config->endpoint('/panel/api/inbounds/'
            . $remoteInboundId . '/resetClientTraffic/' . rawurlencode($clientEmail)));
    }

    /**
     * Resolve the public raw subscription base URL from current 3x-ui settings.
     * Prefer subURI because it also covers reverse-proxy installations.
     */
    private function subscriptionBaseUrl(): string
    {
        if ($this->cachedSubscriptionBaseUrl !== null) {
            return $this->cachedSubscriptionBaseUrl;
        }

        $this->authenticate();
        $decoded = $this->requestJson(
            'POST',
            $this->config->endpoint('/panel/api/setting/all')
        );
        $settings = $decoded['obj'] ?? null;
        if (!is_array($settings) || array_is_list($settings)) {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_invalid_json')
            );
        }

        if (array_key_exists('subEnable', $settings) && empty($settings['subEnable'])) {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_devices_unsupported')
            );
        }

        $configured = trim((string)($settings['subURI'] ?? ''));
        if ($configured !== '') {
            $this->assertPublicSubscriptionBaseUrl($configured);
            return $this->cachedSubscriptionBaseUrl = rtrim($configured, '/');
        }

        $domain = trim((string)($settings['subDomain'] ?? ''));
        if ($domain === '') {
            $domain = trim((string)($settings['webDomain'] ?? ''));
        }

        $host = '';
        if ($domain !== '') {
            $candidate = str_contains($domain, '://') ? $domain : '//' . $domain;
            $parsedHost = parse_url($candidate, PHP_URL_HOST);
            if (is_string($parsedHost)) {
                $host = trim($parsedHost, '[]');
            }
        }
        if ($host === '') {
            $panelHost = parse_url($this->config->panelUrl, PHP_URL_HOST);
            $host = is_string($panelHost) ? trim($panelHost, '[]') : '';
        }
        if ($host === '') {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_devices_unsupported')
            );
        }

        $port = (int)($settings['subPort'] ?? 2096);
        if ($port < 1 || $port > 65535) {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_devices_unsupported')
            );
        }

        $cert = trim((string)($settings['subCertFile'] ?? ''));
        $key = trim((string)($settings['subKeyFile'] ?? ''));
        $scheme = ($cert !== '' && $key !== '') || $port === 443 ? 'https' : 'http';

        $path = trim((string)($settings['subPath'] ?? '/sub/'));
        if ($path === '') {
            $path = '/sub/';
        }
        $path = '/' . trim($path, '/');

        $uriHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $portPart = (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))
            ? ''
            : ':' . $port;

        $base = $scheme . '://' . $uriHost . $portPart . $path;
        $this->assertPublicSubscriptionBaseUrl($base);

        return $this->cachedSubscriptionBaseUrl = rtrim($base, '/');
    }

    private function assertPublicSubscriptionBaseUrl(string $url): void
    {
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
        $host = trim((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_devices_unsupported')
            );
        }
        if (parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null
            || parse_url($url, PHP_URL_FRAGMENT) !== null) {
            throw new ThreeXuiResponseException(
                $this->message('vpn_manager_v2_error_devices_unsupported')
            );
        }
    }

    private function publicSubscriptionHead(string $url, array $deviceHeaders): int
    {
        $headers = $this->forwardedDeviceHeaders($deviceHeaders);

        if ($this->transport !== null) {
            $response = ($this->transport)('HEAD', $url, [], 'public', $headers);
            if (!$response instanceof ThreeXuiHttpResponse) {
                throw new ThreeXuiTransportException(
                    $this->message('vpn_manager_v2_error_transport')
                );
            }
            return $response->status;
        }

        if (!function_exists('curl_init')) {
            throw new ThreeXuiTransportException(
                $this->message('vpn_manager_v2_error_curl_required')
            );
        }

        $addresses = (new NetworkTargetGuard())->validatedRequestAddresses(
            $url,
            $this->config->allowPrivateNetwork
        );

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => max(1, min(30, $this->config->connectTimeout)),
            CURLOPT_TIMEOUT => max(2, min(90, $this->config->readTimeout)),
            CURLOPT_CUSTOMREQUEST => 'HEAD',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => $this->config->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifySsl ? 2 : 0,
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
        $curlError = curl_errno($handle);
        curl_close($handle);

        if ($body === false || $curlError !== 0 || $status === 0) {
            throw new ThreeXuiTransportException(
                $this->message('vpn_manager_v2_error_transport')
            );
        }

        return $status;
    }

    private function forwardedDeviceHeaders(array $headers): array
    {
        $allowed = [
            'X-HWID',
            'X-Device-OS',
            'X-Ver-OS',
            'X-Device-Model',
            'User-Agent',
        ];
        $result = ['Accept: */*'];

        foreach ($allowed as $name) {
            $value = '';
            foreach ($headers as $candidate => $candidateValue) {
                if (strcasecmp((string)$candidate, $name) === 0) {
                    $value = trim((string)$candidateValue);
                    break;
                }
            }
            if ($value === '') {
                continue;
            }
            $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
            $value = mb_substr($value, 0, 512);
            if ($value !== '') {
                $result[] = $name . ': ' . $value;
            }
        }

        if (!array_filter(
            $result,
            static fn(string $line): bool => str_starts_with($line, 'User-Agent:')
        )) {
            $result[] = 'User-Agent: FIREBALL-CMS-VPN-Manager-V2/HWID-Gateway';
        }

        return $result;
    }

    private function fetchInbounds(): array
    {
        return (new ThreeXuiResponseMapper())->inbounds(
            $this->requestJson('GET', $this->config->endpoint('/panel/api/inbounds/list'))
        );
    }

    private function requestJson(
        string $method,
        string $url,
        array $payload = [],
        string $encoding = 'form',
        bool $retryAuthentication = true
    ): array
    {
        try {
            return $this->decodeJsonResponse($this->request($method, $url, $payload, $encoding));
        } catch (ThreeXuiAuthenticationException $exception) {
            if (!$retryAuthentication || $this->config->authType !== 'password') {
                throw $exception;
            }
            $this->authenticated = false;
            $this->cachedInbounds = null;
            $this->resetCookieFile();
            $this->authenticate();

            return $this->requestJson($method, $url, $payload, $encoding, false);
        }
    }

    private function decodeJsonResponse(ThreeXuiHttpResponse $response, bool $authenticationStage = false): array
    {
        if ($response->status === 401) {
            throw new ThreeXuiAuthenticationException($this->message('vpn_manager_v2_error_authentication_failed'));
        }
        if ($response->status === 403) {
            $key = $this->config->authType === 'token'
                ? 'vpn_manager_v2_error_api_token_scope'
                : 'vpn_manager_v2_error_authentication_failed';
            throw new ThreeXuiAuthenticationException($this->message($key));
        }
        if ($response->status < 200 || $response->status >= 300) {
            throw new ThreeXuiHttpException(
                sprintf($this->message('vpn_manager_v2_error_http_status'), $response->status),
                $response->status
            );
        }

        $body = trim($response->body);
        if ($body === '') {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_empty_response'));
        }

        $prefix = strtolower(substr(ltrim($body), 0, 32));
        if (str_contains(strtolower($response->contentType), 'text/html')
            || str_starts_with($prefix, '<!doctype html')
            || str_starts_with($prefix, '<html')) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_html_response'));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_json'));
        }
        if (array_key_exists('success', $decoded) && $decoded['success'] !== true) {
            if ($authenticationStage) {
                throw new ThreeXuiAuthenticationException($this->message('vpn_manager_v2_error_authentication_failed'));
            }

            // FIREBALL_VPN_REPAIR_V2: preserve-api-message
            // 3x-ui returns the actionable reason in `msg`. Keep it, but strip
            // markup/control whitespace and cap the length before logs/UI.
            $detail = trim((string)($decoded['msg'] ?? ''));
            $detail = strip_tags($detail);
            $detail = preg_replace('/\s+/u', ' ', $detail) ?? '';
            $detail = mb_substr(trim($detail), 0, 700);

            throw new ThreeXuiResponseException(
                $detail !== ''
                    ? sprintf($this->message('vpn_manager_v2_error_api_rejected_detail'), $detail)
                    : $this->message('vpn_manager_v2_error_api_rejected')
            );
        }

        return $decoded;
    }

    private function request(string $method, string $url, array $payload = [], string $encoding = 'form'): ThreeXuiHttpResponse
    {
        // Current 3x-ui deliberately masks an unauthenticated API request as
        // HTTP 404 unless it is marked as XMLHttpRequest. Send the header so
        // an expired or replaced token is reported correctly as HTTP 401.
        $headers = ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'];
        if ($this->config->authType === 'token' && $this->config->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->config->token;
            $headers[] = 'X-API-Key: ' . $this->config->token;
        }
        if ($encoding === 'json') {
            $headers[] = 'Content-Type: application/json';
        }
        if ($this->csrfToken !== null
            && !in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true)) {
            $headers[] = 'X-CSRF-Token: ' . $this->csrfToken;
        }

        if ($this->transport !== null) {
            $response = ($this->transport)($method, $url, $payload, $encoding, $headers);
            if (!$response instanceof ThreeXuiHttpResponse) {
                throw new ThreeXuiTransportException($this->message('vpn_manager_v2_error_transport'));
            }
            if (strlen($response->body) > self::MAX_RESPONSE_BYTES) {
                throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_response_too_large'));
            }

            return $response;
        }

        if (!function_exists('curl_init')) {
            throw new ThreeXuiTransportException($this->message('vpn_manager_v2_error_curl_required'));
        }
        $addresses = (new NetworkTargetGuard())->validatedRequestAddresses(
            $url,
            $this->config->allowPrivateNetwork
        );

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => max(1, min(30, $this->config->connectTimeout)),
            CURLOPT_TIMEOUT => max(2, min(90, $this->config->readTimeout)),
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => $this->config->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'FIREBALL-CMS-VPN-Manager-V2/1.4.8',
        ]);
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        }
        $resolve = $this->curlResolveEntries($url, $addresses);
        if ($resolve !== []) {
            // Pin cURL to the addresses that passed validation. A second DNS
            // answer cannot redirect the request to a private service.
            curl_setopt($handle, CURLOPT_RESOLVE, $resolve);
        }

        if ($this->cookieFile !== null) {
            curl_setopt($handle, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($handle, CURLOPT_COOKIEFILE, $this->cookieFile);
        }

        if ($payload !== []) {
            curl_setopt(
                $handle,
                CURLOPT_POSTFIELDS,
                $encoding === 'json' ? $this->encodeJson($payload) : http_build_query($payload)
            );
        }

        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string)(curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: '');
        $curlError = curl_errno($handle);
        curl_close($handle);

        if ($body === false || $curlError !== 0 || $status === 0) {
            throw new ThreeXuiTransportException($this->message('vpn_manager_v2_error_transport'));
        }
        if (strlen((string)$body) > self::MAX_RESPONSE_BYTES) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_response_too_large'));
        }

        return new ThreeXuiHttpResponse($status, $contentType, (string)$body);
    }

    private function ensureCookieFile(): void
    {
        if ($this->cookieFile !== null) {
            return;
        }

        $file = tempnam(sys_get_temp_dir(), 'vpn-v2-3xui-');
        if ($file === false) {
            throw new ThreeXuiTransportException($this->message('vpn_manager_v2_error_cookie_session'));
        }

        @chmod($file, 0600);
        $this->cookieFile = $file;
    }

    private function resetCookieFile(): void
    {
        if ($this->cookieFile !== null && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = null;
        $this->csrfToken = null;
    }

    private function prepareSessionCsrfToken(): void
    {
        try {
            $decoded = $this->decodeJsonResponse(
                $this->request('GET', $this->config->endpoint('/csrf-token'))
            );
            $token = trim((string)($decoded['obj'] ?? ''));
            $this->csrfToken = $token !== '' ? $token : null;
        } catch (ThreeXuiHttpException $exception) {
            if (!$this->isEndpointFallbackStatus($exception)) {
                throw $exception;
            }

            // Older 3x-ui releases do not expose /csrf-token and accept the
            // historical cookie-session requests without the header.
            $this->csrfToken = null;
        }
    }

    private function modernClientInboundIds(string $email): array
    {
        $decoded = $this->requestJson(
            'GET',
            $this->config->endpoint('/panel/api/clients/get/' . rawurlencode($email))
        );
        $payload = $decoded['obj'] ?? [];
        if (!is_array($payload)) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_client_response'));
        }
        $ids = $payload['inboundIds'] ?? $payload['inbound_ids'] ?? [];
        if (!is_array($ids)) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_invalid_client_response'));
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id): bool => $id > 0
        )));
    }

    private function clientInboundIdsFromList(string $clientId, string $email): array
    {
        $ids = [];
        $mapper = new ThreeXuiResponseMapper();
        foreach ($this->fetchInbounds() as $inbound) {
            $inboundId = (int)($inbound['id'] ?? 0);
            if ($inboundId <= 0) {
                continue;
            }
            foreach ($mapper->clients($inbound) as $candidate) {
                $remoteEmail = trim((string)($candidate['email'] ?? ''));
                if (($clientId !== '' && $this->clientCredentialMatches($candidate, $clientId))
                    || ($email !== '' && $remoteEmail !== '' && hash_equals($email, $remoteEmail))) {
                    $ids[$inboundId] = true;
                    break;
                }
            }
        }

        return array_keys($ids);
    }

    private function clientCredentialMatches(array $client, string $expected): bool
    {
        $expected = trim($expected);
        if ($expected === '') {
            return false;
        }

        // Global client records use a numeric database id, while the actual
        // protocol credential is stored in uuid or password. Inbound records
        // may expose the credential directly as id, so accept all three forms.
        foreach (['uuid', 'password', 'id'] as $field) {
            $candidate = trim((string)($client[$field] ?? ''));
            if ($candidate !== '' && hash_equals($candidate, $expected)) {
                return true;
            }
        }

        return false;
    }

    /**
     * FIREBALL_VPN_REPAIR_V3: typed-modern-fields
     *
     * Normalize fields whose REST write representation is stricter than
     * the hydrated client object returned by 3x-ui.
     */
    private function normalizeModernClientPayload(array $client): array
    {
        if (array_key_exists('allowedIPs', $client)) {
            $client['allowedIPs'] = $this->normalizeStringList($client['allowedIPs']);
        }

        if (array_key_exists('allowedIPsByInbound', $client)) {
            $raw = $client['allowedIPsByInbound'];

            if (is_string($raw)) {
                $trimmed = trim($raw);
                if ($trimmed === '') {
                    unset($client['allowedIPsByInbound']);
                    $raw = null;
                } else {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        $raw = $decoded;
                    } else {
                        // Do not send an invalid display string into a typed Go map.
                        unset($client['allowedIPsByInbound']);
                        $raw = null;
                    }
                }
            }

            if (is_array($raw)) {
                $normalized = [];
                foreach ($raw as $inboundId => $values) {
                    $normalized[(string)$inboundId] = $this->normalizeStringList($values);
                }
                $client['allowedIPsByInbound'] = $normalized;
            }
        }

        return $client;
    }

    private function normalizeStringList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            $items = $value;
        } else {
            $string = trim((string)$value);
            if ($string === '') {
                return [];
            }

            $decoded = json_decode($string, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\r\n,;]+/u', $string) ?: [];
            }
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item) || is_object($item)) {
                continue;
            }
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $result[$item] = $item;
        }

        return array_values($result);
    }

    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_json_encode'));
        }

        return $json;
    }

    private function isEndpointFallbackStatus(ThreeXuiHttpException $exception): bool
    {
        return in_array($exception->httpStatus(), [404, 405], true);
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

    private function message(string $key): string
    {
        return \FireballPluginVpnManagerV2::t($key);
    }
}
