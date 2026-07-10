<?php

namespace Fireball\VpnManager\Clients;

use Fireball\VpnManager\Support\Crypto;

final class ThreeXuiClient
{
    private array $server;
    private int $timeout;
    private ?string $cookieFile = null;
    private bool $loggedIn = false;

    public function __construct(array $server, int $timeout = 10)
    {
        $this->server = $server;
        $this->timeout = max(2, min(60, $timeout));
    }

    public function __destruct()
    {
        if ($this->cookieFile !== null && is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function testConnection(): array
    {
        $url = $this->url('/');
        if ($url === '') {
            return ['success' => false, 'message' => 'Panel URL is empty.'];
        }

        try {
            $response = $this->request('GET', $url);
            $success = $response['status'] > 0 && $response['status'] < 500;

            return [
                'success' => $success,
                'message' => $success ? 'Connection established.' : 'Panel returned HTTP ' . $response['status'] . '.',
                'status' => $response['status'],
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'status' => 0,
            ];
        }
    }

    public function listInbounds(): array
    {
        $this->authenticateIfNeeded();

        return $this->requestJson('GET', $this->url('/panel/api/inbounds/list'));
    }

    public function getInbound(int|string $id): array
    {
        $this->authenticateIfNeeded();

        return $this->requestJson('GET', $this->url('/panel/api/inbounds/get/' . rawurlencode((string)$id)));
    }

    public function addClient(int|string $inboundId, array $clientData): array
    {
        $this->authenticateIfNeeded();

        return $this->requestJson('POST', $this->url('/panel/api/inbounds/addClient'), [
            'id' => $inboundId,
            'settings' => json_encode(['clients' => [$clientData]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function updateClient(int|string $inboundId, int|string $clientId, array $clientData): array
    {
        $this->authenticateIfNeeded();

        return $this->requestJson('POST', $this->url('/panel/api/inbounds/updateClient/' . rawurlencode((string)$clientId)), [
            'id' => $inboundId,
            'settings' => json_encode(['clients' => [$clientData]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function deleteClient(int|string $inboundId, int|string $clientId): array
    {
        $this->authenticateIfNeeded();

        return $this->requestJson('POST', $this->url('/panel/api/inbounds/' . rawurlencode((string)$inboundId) . '/delClient/' . rawurlencode((string)$clientId)));
    }

    public function enableClient(int|string $inboundId, int|string $clientId): array
    {
        $client = $this->findClient($inboundId, (string)$clientId);
        $client['enable'] = true;

        return $this->updateClient($inboundId, $clientId, $client);
    }

    public function disableClient(int|string $inboundId, int|string $clientId): array
    {
        $client = $this->findClient($inboundId, (string)$clientId);
        $client['enable'] = false;

        return $this->updateClient($inboundId, $clientId, $client);
    }

    public function resetClientTraffic(int|string $inboundId, int|string $clientId): array
    {
        $this->authenticateIfNeeded();

        return $this->requestJson('POST', $this->url('/panel/api/inbounds/' . rawurlencode((string)$inboundId) . '/resetClientTraffic/' . rawurlencode((string)$clientId)));
    }

    public function getClientTraffic(int|string $clientId): array
    {
        $this->authenticateIfNeeded();

        return $this->requestJson('GET', $this->url('/panel/api/inbounds/getClientTraffics/' . rawurlencode((string)$clientId)));
    }

    public function clientExists(int|string $inboundId, string $clientEmail, string $clientUuid): bool
    {
        return $this->findClient($inboundId, $clientUuid, $clientEmail, false) !== null;
    }

    private function findClient(int|string $inboundId, string $clientId, string $clientEmail = '', bool $throw = true): ?array
    {
        $response = $this->getInbound($inboundId);
        $inbound = is_array($response['obj'] ?? null) ? $response['obj'] : $response;
        $settings = $inbound['settings'] ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        foreach ((array)($settings['clients'] ?? []) as $client) {
            if (!is_array($client)) {
                continue;
            }

            $email = (string)($client['email'] ?? '');
            $id = (string)($client['id'] ?? $client['uuid'] ?? $client['password'] ?? '');
            if (($clientId !== '' && $id === $clientId) || ($clientEmail !== '' && $email === $clientEmail)) {
                return $client;
            }
        }

        if ($throw) {
            throw new \RuntimeException(\FireballPluginVpnManager::t('vpn_manager_error_client_not_confirmed'));
        }

        return null;
    }

    private function requestJson(string $method, string $url, array $payload = []): array
    {
        $response = $this->request($method, $url, $payload);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('3x-ui returned a non-JSON response.');
        }
        if (array_key_exists('success', $decoded) && empty($decoded['success'])) {
            $message = trim((string)($decoded['msg'] ?? $decoded['message'] ?? '3x-ui API request failed.'));
            throw new \RuntimeException($message !== '' ? $message : '3x-ui API request failed.');
        }

        return $decoded;
    }

    private function request(string $method, string $url, array $payload = []): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP curl extension is required for 3x-ui requests.');
        }

        $headers = ['Accept: application/json'];
        $token = Crypto::decrypt($this->server['api_token_encrypted'] ?? null);
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($this->cookieFile !== null) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        }

        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException($error !== '' ? $error : '3x-ui request failed.');
        }

        return [
            'status' => $status,
            'body' => (string)$body,
        ];
    }

    private function authenticateIfNeeded(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $username = Crypto::decrypt($this->server['username_encrypted'] ?? null);
        $password = Crypto::decrypt($this->server['password_encrypted'] ?? null);
        if ($username === '' || $password === '') {
            $this->loggedIn = true;
            return;
        }

        $this->cookieFile = tempnam(sys_get_temp_dir(), 'vpn-3xui-cookie-') ?: null;
        $response = $this->request('POST', $this->url('/login'), [
            'username' => $username,
            'password' => $password,
        ]);
        if ($response['status'] < 200 || $response['status'] >= 400) {
            throw new \RuntimeException('3x-ui login failed with HTTP ' . $response['status'] . '.');
        }

        $decoded = json_decode($response['body'], true);
        if (is_array($decoded) && array_key_exists('success', $decoded) && empty($decoded['success'])) {
            throw new \RuntimeException((string)($decoded['msg'] ?? '3x-ui login failed.'));
        }

        $this->loggedIn = true;
    }

    private function url(string $path): string
    {
        $base = rtrim((string)($this->server['panel_url'] ?? ''), '/');
        if ($base === '') {
            return '';
        }

        $panelPath = trim((string)($this->server['panel_path'] ?? ''), '/');
        $path = '/' . ltrim($path, '/');

        return $base . ($panelPath !== '' ? '/' . $panelPath : '') . $path;
    }
}
