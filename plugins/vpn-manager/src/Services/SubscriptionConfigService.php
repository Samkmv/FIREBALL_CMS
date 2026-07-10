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

    public function encodedPayload(array $subscription): string
    {
        $uris = $this->uris($subscription);
        if (!$uris) {
            return '';
        }

        return base64_encode(implode("\n", $uris));
    }

    public function uris(array $subscription): array
    {
        $subscriptionId = (int)($subscription['id'] ?? 0);
        if ($subscriptionId <= 0) {
            return [];
        }

        $uris = [];
        foreach ($this->repo->subscriptionConfigNodes($subscriptionId) as $node) {
            $uri = $this->uriForNode($node);
            if ($uri !== '') {
                $uris[] = $uri;
            }
        }

        return $uris;
    }

    private function uriForNode(array $node): string
    {
        $protocol = strtolower(trim((string)($node['protocol'] ?? '')));
        $host = $this->publicHost($node);
        $port = (int)($node['port'] ?? 0);
        $id = trim((string)($node['client_uuid'] ?? ''));
        if ($protocol === '' || $host === '' || $port <= 0 || $id === '') {
            return '';
        }

        $settings = $this->json((string)($node['settings_json'] ?? ''));
        $stream = $this->json((string)($node['stream_settings_json'] ?? ''));
        $client = $this->clientSettings($settings, (string)($node['client_email'] ?? ''), $id);
        $name = trim((string)($node['subscription_item_name'] ?? ''));
        if ($name === '') {
            $name = (new SubscriptionLinkService())->configName((string)($node['server_name'] ?? $node['inbound_name'] ?? 'VPN'));
        }

        return match ($protocol) {
            'vless' => $this->vless($host, $port, $id, $name, $stream, $client),
            'vmess' => $this->vmess($host, $port, $id, $name, $stream, $client),
            'trojan' => $this->trojan($host, $port, (string)($client['password'] ?? $id), $name, $stream),
            default => '',
        };
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

        return 'trojan://' . rawurlencode($password) . '@' . $this->hostForUri($host) . ':' . $port . '?' . $this->query($params) . '#' . rawurlencode($name);
    }

    private function addSecurityParams(array &$params, array $stream, string $security): void
    {
        if ($security === 'reality') {
            $reality = is_array($stream['realitySettings'] ?? null) ? $stream['realitySettings'] : [];
            $params['pbk'] = (string)($reality['publicKey'] ?? '');
            $params['fp'] = (string)($reality['fingerprint'] ?? 'chrome');
            $params['sni'] = $this->first((array)($reality['serverNames'] ?? []));
            $params['sid'] = $this->first((array)($reality['shortIds'] ?? []));
            $params['spx'] = (string)($reality['spiderX'] ?? '/');
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
        foreach ((array)($settings['clients'] ?? []) as $client) {
            if (!is_array($client)) {
                continue;
            }

            if ((string)($client['email'] ?? '') === $email || (string)($client['id'] ?? $client['password'] ?? '') === $id) {
                return $client;
            }
        }

        return [];
    }

    private function publicHost(array $node): string
    {
        $host = trim((string)($node['public_host'] ?? ''));
        if ($host !== '') {
            return $this->normalizeHost($host);
        }

        return $this->normalizeHost((string)($node['panel_url'] ?? ''));
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
}
