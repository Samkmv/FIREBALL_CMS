<?php

namespace Fireball\VpnManager\Clients;

use Fireball\VpnManager\Support\Crypto;

final class ThreeXuiClient
{
    private array $server;
    private int $timeout;

    public function __construct(array $server, int $timeout = 10)
    {
        $this->server = $server;
        $this->timeout = max(2, min(60, $timeout));
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
        return $this->requestJson('GET', $this->url('/panel/api/inbounds/list'));
    }

    public function getInbound(int|string $id): array
    {
        return $this->requestJson('GET', $this->url('/panel/api/inbounds/get/' . rawurlencode((string)$id)));
    }

    public function addClient(int|string $inboundId, array $clientData): array
    {
        return $this->requestJson('POST', $this->url('/panel/api/inbounds/addClient'), [
            'id' => $inboundId,
            'settings' => json_encode(['clients' => [$clientData]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function updateClient(int|string $inboundId, int|string $clientId, array $clientData): array
    {
        return $this->requestJson('POST', $this->url('/panel/api/inbounds/updateClient/' . rawurlencode((string)$clientId)), [
            'id' => $inboundId,
            'settings' => json_encode(['clients' => [$clientData]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function deleteClient(int|string $inboundId, int|string $clientId): array
    {
        return $this->requestJson('POST', $this->url('/panel/api/inbounds/' . rawurlencode((string)$inboundId) . '/delClient/' . rawurlencode((string)$clientId)));
    }

    public function enableClient(int|string $inboundId, int|string $clientId): array
    {
        return $this->updateClient($inboundId, $clientId, ['id' => $clientId, 'enable' => true]);
    }

    public function disableClient(int|string $inboundId, int|string $clientId): array
    {
        return $this->updateClient($inboundId, $clientId, ['id' => $clientId, 'enable' => false]);
    }

    public function resetClientTraffic(int|string $inboundId, int|string $clientId): array
    {
        return $this->requestJson('POST', $this->url('/panel/api/inbounds/' . rawurlencode((string)$inboundId) . '/resetClientTraffic/' . rawurlencode((string)$clientId)));
    }

    public function getClientTraffic(int|string $clientId): array
    {
        return $this->requestJson('GET', $this->url('/panel/api/inbounds/getClientTraffics/' . rawurlencode((string)$clientId)));
    }

    private function requestJson(string $method, string $url, array $payload = []): array
    {
        $response = $this->request($method, $url, $payload);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('3x-ui returned a non-JSON response.');
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
