<?php

namespace Fireball\VpnManager\Services;

use Fireball\VpnManager\Repositories\VpnRepository;

final class SubscriptionConfigService
{
    private VpnRepository $repo;

    public function __construct(?VpnRepository $repo = null)
    {
        $this->repo = $repo ?: new VpnRepository();
    }

    public function encodedPayload(array $subscription, bool $logErrors = false): string
    {
        $uris = $this->uris($subscription, $logErrors);
        if (!$uris) {
            return '';
        }

        return base64_encode(implode("\n", $uris) . "\n");
    }

    public function uris(array $subscription, bool $logErrors = false): array
    {
        $subscriptionId = (int)($subscription['id'] ?? 0);
        if ($subscriptionId <= 0) {
            return [];
        }

        $uris = [];
        foreach ($this->repo->subscriptionConfigNodes($subscriptionId) as $node) {
            $uri = $this->uriForNode($node, $logErrors, (int)($subscription['user_id'] ?? 0), $subscriptionId);
            if ($uri !== '') {
                $uris[] = $uri;
            }
        }

        return $uris;
    }

    private function uriForNode(array $node, bool $logErrors, int $userId, int $subscriptionId): string
    {
        $protocol = strtolower(trim((string)($node['protocol'] ?? '')));
        $host = $this->publicHost($node);
        $port = (int)($node['port'] ?? 0);
        $id = trim((string)($node['client_uuid'] ?? ''));
        $missing = [];
        if ($protocol === '') {
            $missing[] = 'protocol';
        }
        if ($host === '') {
            $missing[] = 'host';
        }
        if ($port <= 0) {
            $missing[] = 'port';
        }
        if ($id === '') {
            $missing[] = 'client_uuid';
        }
        if ($missing) {
            $this->logConfigError($logErrors, $userId, $subscriptionId, $node, 'Missing config fields: ' . implode(', ', $missing));

            return '';
        }

        $settings = $this->json((string)($node['settings_json'] ?? ''));
        $stream = $this->json((string)($node['stream_settings_json'] ?? ''));
        $streamMissing = $this->requiredStreamFields($protocol, $stream);
        if ($streamMissing) {
            $this->logConfigError($logErrors, $userId, $subscriptionId, $node, 'Missing config fields: ' . implode(', ', $streamMissing));

            return '';
        }

        $client = $this->clientSettings($settings, (string)($node['client_email'] ?? ''), $id);
        $name = trim((string)($node['subscription_item_name'] ?? ''));
        if ($name === '') {
            $name = (new SubscriptionLinkService())->configName((string)($node['server_name'] ?? $node['inbound_name'] ?? 'VPN'));
        }

        $uri = match ($protocol) {
            'vless' => $this->vless($host, $port, $id, $name, $stream, $client),
            'vmess' => $this->vmess($host, $port, $id, $name, $stream, $client),
            'trojan' => $this->trojan($host, $port, (string)($client['password'] ?? $id), $name, $stream),
            default => '',
        };
        if ($uri === '') {
            $this->logConfigError($logErrors, $userId, $subscriptionId, $node, 'Unsupported inbound protocol: ' . $protocol);
        }

        return $uri;
    }

    private function vless(string $host, int $port, string $id, string $name, array $stream, array $client): string
    {
        $network = (string)($stream['network'] ?? 'tcp');
        $security = (string)($stream['security'] ?? 'none');
        $params = [
            'encryption' => (string)($client['encryption'] ?? 'none'),
            'security' => $security !== '' ? $security : 'none',
            'type' => $network !== '' ? $network : 'tcp',
        ];
        if (!empty($client['flow'])) {
            $params['flow'] = (string)$client['flow'];
        }

        $this->addSecurityParams($params, $stream, $security);
        $this->addTransportParams($params, $stream, $network);
        if (($params['headerType'] ?? '') === 'none') {
            unset($params['headerType']);
        }

        return 'vless://' . rawurlencode($id) . '@' . $this->hostForUri($host) . ':' . $port . '?' . $this->query($params) . '#' . rawurlencode($name);
    }

    private function vmess(string $host, int $port, string $id, string $name, array $stream, array $client): string
    {
        $network = (string)($stream['network'] ?? 'tcp');
        $security = (string)($stream['security'] ?? '');
        $params = [
            'v' => '2',
            'ps' => $name,
            'add' => $host,
            'port' => (string)$port,
            'id' => $id,
            'aid' => (string)($client['alterId'] ?? $client['aid'] ?? '0'),
            'scy' => (string)($client['security'] ?? 'auto'),
            'net' => $network !== '' ? $network : 'tcp',
            'type' => '',
            'host' => '',
            'path' => '',
            'tls' => in_array($security, ['tls', 'reality'], true) ? $security : '',
            'sni' => '',
            'alpn' => '',
            'fp' => '',
        ];

        $this->fillVmessSecurity($params, $stream, $security);
        $this->fillVmessTransport($params, $stream, $network);

        return 'vmess://' . base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function trojan(string $host, int $port, string $password, string $name, array $stream): string
    {
        $network = (string)($stream['network'] ?? 'tcp');
        $security = (string)($stream['security'] ?? 'tls');
        $params = [
            'security' => $security !== '' ? $security : 'tls',
            'type' => $network !== '' ? $network : 'tcp',
        ];

        $this->addSecurityParams($params, $stream, $security);
        $this->addTransportParams($params, $stream, $network);
        if (($params['headerType'] ?? '') === 'none') {
            unset($params['headerType']);
        }

        return 'trojan://' . rawurlencode($password) . '@' . $this->hostForUri($host) . ':' . $port . '?' . $this->query($params) . '#' . rawurlencode($name);
    }

    private function addSecurityParams(array &$params, array $stream, string $security): void
    {
        if ($security === 'reality') {
            $reality = is_array($stream['realitySettings'] ?? null) ? $stream['realitySettings'] : [];
            $realityClient = is_array($reality['settings'] ?? null) ? $reality['settings'] : [];
            $params['pbk'] = $this->firstValue([
                $reality['publicKey'] ?? null,
                $reality['public_key'] ?? null,
                $realityClient['publicKey'] ?? null,
                $realityClient['public_key'] ?? null,
            ]);
            $params['fp'] = $this->firstValue([
                $reality['fingerprint'] ?? null,
                $realityClient['fingerprint'] ?? null,
                'chrome',
            ]);
            $params['sni'] = $this->firstValue([
                $realityClient['serverName'] ?? null,
                $realityClient['server_name'] ?? null,
                $reality['serverName'] ?? null,
                $reality['server_name'] ?? null,
            ]);
            if ($params['sni'] === '') {
                $params['sni'] = $this->first((array)($reality['serverNames'] ?? []));
            }
            $params['sid'] = $this->first((array)($reality['shortIds'] ?? $reality['short_ids'] ?? []));
            $params['spx'] = $this->firstValue([
                $reality['spiderX'] ?? null,
                $reality['spider_x'] ?? null,
                $realityClient['spiderX'] ?? null,
                $realityClient['spider_x'] ?? null,
                '/',
            ]);
            if (!empty($reality['alpn'])) {
                $params['alpn'] = is_array($reality['alpn']) ? implode(',', $reality['alpn']) : (string)$reality['alpn'];
            }
            return;
        }

        if ($security === 'tls') {
            $tls = is_array($stream['tlsSettings'] ?? null) ? $stream['tlsSettings'] : [];
            $params['sni'] = (string)($tls['serverName'] ?? '');
            $params['fp'] = (string)($tls['fingerprint'] ?? '');
            if (!empty($tls['alpn'])) {
                $params['alpn'] = is_array($tls['alpn']) ? implode(',', $tls['alpn']) : (string)$tls['alpn'];
            }
            if (!empty($tls['allowInsecure'])) {
                $params['allowInsecure'] = '1';
            }
        }
    }

    private function addTransportParams(array &$params, array $stream, string $network): void
    {
        $network = strtolower($network !== '' ? $network : 'tcp');
        if ($network === 'ws') {
            $ws = is_array($stream['wsSettings'] ?? null) ? $stream['wsSettings'] : [];
            $params['path'] = (string)($ws['path'] ?? '/');
            $params['host'] = (string)($ws['headers']['Host'] ?? '');
            return;
        }

        if ($network === 'grpc') {
            $grpc = is_array($stream['grpcSettings'] ?? null) ? $stream['grpcSettings'] : [];
            $params['serviceName'] = (string)($grpc['serviceName'] ?? '');
            $params['mode'] = !empty($grpc['multiMode']) ? 'multi' : 'gun';
            return;
        }

        if ($network === 'kcp') {
            $kcp = is_array($stream['kcpSettings'] ?? null) ? $stream['kcpSettings'] : [];
            $params['headerType'] = (string)($kcp['header']['type'] ?? 'none');
            $params['seed'] = (string)($kcp['seed'] ?? '');
            return;
        }

        if (in_array($network, ['httpupgrade', 'xhttp', 'splithttp'], true)) {
            $key = $network === 'httpupgrade' ? 'httpupgradeSettings' : ($network === 'xhttp' ? 'xhttpSettings' : 'splithttpSettings');
            $http = is_array($stream[$key] ?? null) ? $stream[$key] : [];
            $params['path'] = (string)($http['path'] ?? '/');
            $params['host'] = (string)($http['host'] ?? '');
            return;
        }

        $tcp = is_array($stream['tcpSettings'] ?? null) ? $stream['tcpSettings'] : [];
        $header = is_array($tcp['header'] ?? null) ? $tcp['header'] : [];
        $params['headerType'] = (string)($header['type'] ?? 'none');
        if (($header['type'] ?? '') === 'http') {
            $request = is_array($header['request'] ?? null) ? $header['request'] : [];
            $params['path'] = $this->first((array)($request['path'] ?? []));
            $params['host'] = $this->first((array)($request['headers']['Host'] ?? []));
        }
    }

    private function fillVmessSecurity(array &$params, array $stream, string $security): void
    {
        $securityParams = [];
        $this->addSecurityParams($securityParams, $stream, $security);
        foreach (['sni', 'alpn', 'fp'] as $key) {
            if (!empty($securityParams[$key])) {
                $params[$key] = (string)$securityParams[$key];
            }
        }
    }

    private function fillVmessTransport(array &$params, array $stream, string $network): void
    {
        $transport = [];
        $this->addTransportParams($transport, $stream, $network);
        $params['type'] = (string)($transport['headerType'] ?? '');
        $params['host'] = (string)($transport['host'] ?? '');
        $params['path'] = (string)($transport['path'] ?? $transport['serviceName'] ?? '');
    }

    private function clientSettings(array $settings, string $email, string $id): array
    {
        $fallback = [];
        foreach ((array)($settings['clients'] ?? []) as $client) {
            if (!is_array($client)) {
                continue;
            }

            if (!$fallback) {
                foreach (['flow', 'encryption', 'alterId', 'aid', 'security'] as $key) {
                    if (array_key_exists($key, $client)) {
                        $fallback[$key] = $client[$key];
                    }
                }
            }

            if ((string)($client['email'] ?? '') === $email || (string)($client['id'] ?? $client['password'] ?? '') === $id) {
                return $client;
            }
        }

        return $fallback;
    }

    private function requiredStreamFields(string $protocol, array $stream): array
    {
        $missing = [];
        $security = strtolower((string)($stream['security'] ?? ''));
        if ($protocol === 'vless' && $security === 'reality') {
            $reality = is_array($stream['realitySettings'] ?? null) ? $stream['realitySettings'] : [];
            $realityClient = is_array($reality['settings'] ?? null) ? $reality['settings'] : [];
            if ($this->firstValue([
                $reality['publicKey'] ?? null,
                $reality['public_key'] ?? null,
                $realityClient['publicKey'] ?? null,
                $realityClient['public_key'] ?? null,
            ]) === '') {
                $missing[] = 'realitySettings.publicKey';
            }
            if ($this->firstValue([
                $realityClient['serverName'] ?? null,
                $realityClient['server_name'] ?? null,
                $reality['serverName'] ?? null,
                $reality['server_name'] ?? null,
                $this->first((array)($reality['serverNames'] ?? [])),
            ]) === '') {
                $missing[] = 'realitySettings.serverNames';
            }
        }

        return $missing;
    }

    private function publicHost(array $node): string
    {
        $host = trim((string)($node['public_host'] ?? ''));
        if ($host !== '') {
            return $this->normalizeHost($host);
        }

        $panelHost = trim((string)($node['panel_url'] ?? ''));
        return $panelHost !== '' ? $this->normalizeHost($panelHost) : '';
    }

    private function normalizeHost(string $value): string
    {
        $value = trim($value);
        $host = parse_url($value, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        return trim($value, " \t\n\r\0\x0B/");
    }

    private function hostForUri(string $host): string
    {
        return str_contains($host, ':') && !str_starts_with($host, '[') ? '[' . $host . ']' : $host;
    }

    private function json(string $value): array
    {
        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function query(array $params): string
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $filtered[$key] = $value;
        }

        return http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    }

    private function first(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function firstValue(array $values): string
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = $this->first($value);
            }

            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function logConfigError(bool $enabled, int $userId, int $subscriptionId, array $node, string $message): void
    {
        if (!$enabled) {
            return;
        }

        $this->repo->logEvent('subscription_config_error', 'VPN subscription config generation failed for a node.', [
            'node_id' => (int)($node['id'] ?? 0),
            'server_id' => (int)($node['server_id'] ?? 0),
            'inbound_id' => (int)($node['inbound_id'] ?? 0),
            'error_message' => $message,
        ], $userId ?: null, $subscriptionId ?: null, (int)($node['id'] ?? 0), (int)($node['server_id'] ?? 0));
    }
}
