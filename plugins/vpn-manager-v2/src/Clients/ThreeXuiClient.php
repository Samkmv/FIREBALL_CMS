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
    private bool $authenticated = false;
    private ?array $cachedInbounds = null;

    public function __construct(private readonly ThreeXuiServerConfig $config)
    {
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
        $response = $this->request('POST', $this->config->endpoint('/login'), [
            'username' => $this->config->username,
            'password' => $this->config->password,
        ]);
        $decoded = $this->decodeJsonResponse($response, true);
        if (($decoded['success'] ?? null) !== true) {
            throw new ThreeXuiAuthenticationException($this->message('vpn_manager_v2_error_authentication_failed'));
        }

        $this->authenticated = true;
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

            $id = (string)($client['id'] ?? $client['uuid'] ?? $client['password'] ?? '');
            $email = (string)($client['email'] ?? '');
            if (($clientId !== '' && hash_equals($id, $clientId)) || ($clientEmail !== '' && hash_equals($email, $clientEmail))) {
                return $client;
            }
        }

        return null;
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
        $this->authenticate();
        $email = trim((string)($client['email'] ?? ''));

        if ($email !== '') {
            try {
                return $this->requestJson(
                    'POST',
                    $this->config->endpoint('/panel/api/clients/update/' . rawurlencode($email))
                        . '?inboundIds=' . rawurlencode((string)$remoteInboundId),
                    $client,
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
                return $this->requestJson('POST', $this->config->endpoint('/panel/api/clients/del/' . rawurlencode($email)), [], 'json');
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

        return $this->requestJson(
            'POST',
            $this->config->endpoint('/panel/api/inbounds/' . $remoteInboundId
                . '/resetClientTraffic/' . rawurlencode($clientEmail))
        );
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
        if ($response->status === 401 || $response->status === 403) {
            throw new ThreeXuiAuthenticationException($this->message('vpn_manager_v2_error_authentication_failed'));
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
            throw new ThreeXuiResponseException($this->message('vpn_manager_v2_error_api_rejected'));
        }

        return $decoded;
    }

    private function request(string $method, string $url, array $payload = [], string $encoding = 'form'): ThreeXuiHttpResponse
    {
        if (!function_exists('curl_init')) {
            throw new ThreeXuiTransportException($this->message('vpn_manager_v2_error_curl_required'));
        }
        $addresses = (new NetworkTargetGuard())->validatedRequestAddresses(
            $url,
            $this->config->allowPrivateNetwork
        );

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
            CURLOPT_USERAGENT => 'FIREBALL-CMS-VPN-Manager-V2/0.19.5',
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
