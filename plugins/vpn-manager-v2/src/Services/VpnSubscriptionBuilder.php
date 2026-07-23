<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Repositories\SubscriptionConfigRepository;

final class VpnSubscriptionBuilder
{
    public function __construct(
        private readonly ?SubscriptionConfigRepository $repository = null,
        private readonly ?VpnConfigValidator $validator = null,
        private readonly ?VpnFlowResolver $flowResolver = null,
        private readonly ?VpnServerNameRenderer $nameRenderer = null,
        private readonly ?SettingsService $settings = null,
        private readonly ?VpnV2SubscriptionDependencyService $dependencies = null,
        private readonly ?ExternalVpnSourceService $externalSources = null,
    ) {
    }

    public function build(array $subscription): array
    {
        $dependencies = $this->dependencies ?? new VpnV2SubscriptionDependencyService(
            config: $this->repository ?? new SubscriptionConfigRepository()
        );
        $firstPartyUris = $this->buildFromNodes(
            $subscription,
            $dependencies->collectEffectiveConnections($subscription)
        );
        $external = $this->externalSources ?? new ExternalVpnSourceService();
        $externalUris = [];
        foreach ($dependencies->collectEffectiveSubscriptionIds($subscription) as $subscriptionId) {
            array_push($externalUris, ...$external->urisForParent($subscriptionId));
        }

        return $this->mergeUris($firstPartyUris, $externalUris);
    }

    /**
     * First-party configurations always win technical duplicate conflicts.
     * External sources may append new configurations, but must never replace
     * an existing connection or its administrator-defined display name.
     */
    public function mergeUris(array $firstPartyUris, array $externalUris): array
    {
        $external = $this->externalSources ?? new ExternalVpnSourceService();
        $unique = [];
        foreach ([$firstPartyUris, $externalUris] as $group) {
            foreach ($group as $uri) {
                if (!is_string($uri) || trim($uri) === '') {
                    continue;
                }
                $technicalKey = $external->technicalKey($uri);
                if (!array_key_exists($technicalKey, $unique)) {
                    $unique[$technicalKey] = $uri;
                }
            }
        }

        return array_values($unique);
    }

    public function buildFromNodes(array $subscription, array $nodes): array
    {
        if ((int)($subscription['id'] ?? 0) <= 0) {
            throw new \Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_config_invalid')
            );
        }

        $uris = [];
        $settings = ($this->settings ?? new SettingsService())->current();
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                throw new \Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException(
                    \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_config_invalid')
                );
            }
            $uri = $this->buildNode($node, $settings);
            $uris[$uri] = $uri;
        }

        return array_values($uris);
    }

    private function buildNode(array $node, array $settings): string
    {
        $stream = $this->json((string)($node['stream_settings_json'] ?? ''));
        $protocol = strtolower(trim((string)($node['protocol'] ?? '')));
        $network = strtolower(trim((string)($node['network'] ?? $stream['network'] ?? 'tcp')));
        $security = strtolower(trim((string)($node['security'] ?? $stream['security'] ?? 'none')));
        $host = $this->serverHost((string)($node['panel_url'] ?? ''));
        $flow = ($this->flowResolver ?? new VpnFlowResolver())->normalizeFlow($node['flow'] ?? null);
        $params = [
            'security' => $security !== '' ? $security : 'none',
            'type' => $network !== '' ? $network : 'tcp',
        ];
        if ($protocol === 'vless') {
            $params['encryption'] = 'none';
        }
        if ($flow !== null) {
            $params['flow'] = $flow;
        }

        $this->addSecurity($params, $stream, $security, $host);
        $this->addTransport($params, $stream, $network);
        $params = $this->filterParams($params);

        $credential = (new RemoteClientCredentialService())->credential($node);
        $validationNode = array_replace($node, [
            'protocol' => $protocol,
            'network' => $network,
            'security' => $security,
            'flow' => $flow,
            'config_host' => $host,
            'client_uuid' => $credential,
        ]);
        $validator = $this->validator ?? new VpnConfigValidator($this->flowResolver ?? new VpnFlowResolver());
        $validator->validate($validationNode, $stream, $params);

        $displayName = $this->displayName($node, $settings);
        $uri = match ($protocol) {
            'vless' => 'vless://' . rawurlencode($credential)
                . '@' . $this->hostForUri($host)
                . ':' . (int)$node['port']
                . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986)
                . '#' . rawurlencode($displayName),
            'trojan' => 'trojan://' . rawurlencode($credential)
                . '@' . $this->hostForUri($host)
                . ':' . (int)$node['port']
                . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986)
                . '#' . rawurlencode($displayName),
            'vmess' => $this->vmessUri($credential, $host, (int)$node['port'], $displayName, $params),
            default => '',
        };
        $validator->validateUri($uri);

        return $uri;
    }

    private function vmessUri(string $credential, string $host, int $port, string $displayName, array $params): string
    {
        $payload = [
            'v' => '2',
            'ps' => $displayName,
            'add' => $host,
            'port' => (string)$port,
            'id' => $credential,
            'aid' => '0',
            'scy' => 'auto',
            'net' => (string)($params['type'] ?? 'tcp'),
            'type' => (string)($params['headerType'] ?? 'none'),
            'host' => (string)($params['host'] ?? ''),
            'path' => (string)($params['path'] ?? $params['serviceName'] ?? ''),
            'tls' => (string)($params['security'] ?? 'none') === 'none' ? '' : 'tls',
            'sni' => (string)($params['sni'] ?? ''),
            'alpn' => (string)($params['alpn'] ?? ''),
            'fp' => (string)($params['fp'] ?? ''),
        ];

        return 'vmess://' . base64_encode(json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    private function addSecurity(array &$params, array $stream, string $security, string $host): void
    {
        if ($security === 'reality') {
            $reality = is_array($stream['realitySettings'] ?? null) ? $stream['realitySettings'] : [];
            $settings = is_array($reality['settings'] ?? null) ? $reality['settings'] : [];
            $params['sni'] = $this->firstValue([
                $settings['serverName'] ?? null,
                $reality['serverName'] ?? null,
                $reality['serverNames'] ?? null,
            ]);
            $params['fp'] = $this->firstValue([
                $settings['fingerprint'] ?? null,
                $reality['fingerprint'] ?? null,
                'chrome',
            ]);
            $params['pbk'] = $this->firstValue([
                $settings['publicKey'] ?? null,
                $reality['publicKey'] ?? null,
            ]);
            $params['sid'] = $this->firstValue([
                $reality['shortIds'] ?? null,
                $reality['shortId'] ?? null,
                $settings['shortId'] ?? null,
            ]);
            $params['spx'] = $this->firstValue([
                $settings['spiderX'] ?? null,
                $reality['spiderX'] ?? null,
            ]);
            $params['pqv'] = $this->firstValue([
                $settings['mldsa65Verify'] ?? null,
                $reality['mldsa65Verify'] ?? null,
            ]);

            return;
        }

        if ($security === 'tls') {
            $tls = is_array($stream['tlsSettings'] ?? null) ? $stream['tlsSettings'] : [];
            $settings = is_array($tls['settings'] ?? null) ? $tls['settings'] : [];
            $params['sni'] = $this->firstValue([$tls['serverName'] ?? null, $host]);
            $params['fp'] = $this->firstValue([$settings['fingerprint'] ?? null, $tls['fingerprint'] ?? null]);
            if (is_array($tls['alpn'] ?? null)) {
                $params['alpn'] = implode(',', array_values(array_filter(array_map('strval', $tls['alpn']))));
            }
            if (!empty($tls['allowInsecure'])) {
                $params['allowInsecure'] = '1';
            }
        }
    }

    private function addTransport(array &$params, array $stream, string $network): void
    {
        if (in_array($network, ['tcp', 'raw'], true)) {
            $transport = $network === 'raw'
                ? ($stream['rawSettings'] ?? $stream['tcpSettings'] ?? [])
                : ($stream['tcpSettings'] ?? $stream['rawSettings'] ?? []);
            $transport = is_array($transport) ? $transport : [];
            $header = is_array($transport['header'] ?? null) ? $transport['header'] : [];
            if (strtolower((string)($header['type'] ?? 'none')) === 'http') {
                $request = is_array($header['request'] ?? null) ? $header['request'] : [];
                $params['headerType'] = 'http';
                $params['path'] = $this->firstValue([$request['path'] ?? null]);
                $params['host'] = $this->headerHost($request['headers'] ?? []);
            }

            return;
        }

        if (in_array($network, ['xhttp', 'splithttp'], true)) {
            $xhttp = $stream['xhttpSettings'] ?? $stream['splithttpSettings'] ?? [];
            $xhttp = is_array($xhttp) ? $xhttp : [];
            $params['path'] = $this->firstValue([$xhttp['path'] ?? null, '/']);
            $params['host'] = $this->firstValue([$xhttp['host'] ?? null, $this->headerHost($xhttp['headers'] ?? [])]);
            $params['mode'] = $this->firstValue([$xhttp['mode'] ?? null, 'auto']);
            if ($this->firstValue([$xhttp['xPaddingBytes'] ?? null]) !== '') {
                $params['x_padding_bytes'] = (string)$xhttp['xPaddingBytes'];
            }
            $extra = $this->xhttpExtra($xhttp);
            if ($extra !== []) {
                $params['extra'] = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            return;
        }

        if ($network === 'ws') {
            $ws = is_array($stream['wsSettings'] ?? null) ? $stream['wsSettings'] : [];
            $params['path'] = $this->firstValue([$ws['path'] ?? null, '/']);
            $params['host'] = $this->firstValue([$ws['host'] ?? null, $this->headerHost($ws['headers'] ?? [])]);

            return;
        }

        if ($network === 'grpc') {
            $grpc = is_array($stream['grpcSettings'] ?? null) ? $stream['grpcSettings'] : [];
            $params['serviceName'] = (string)($grpc['serviceName'] ?? '');
            $params['authority'] = (string)($grpc['authority'] ?? '');
            if (!empty($grpc['multiMode'])) {
                $params['mode'] = 'multi';
            }

            return;
        }

        if ($network === 'httpupgrade') {
            $upgrade = is_array($stream['httpupgradeSettings'] ?? null) ? $stream['httpupgradeSettings'] : [];
            $params['path'] = $this->firstValue([$upgrade['path'] ?? null, '/']);
            $params['host'] = $this->firstValue([$upgrade['host'] ?? null, $this->headerHost($upgrade['headers'] ?? [])]);
        }
    }

    private function xhttpExtra(array $xhttp): array
    {
        $extra = [];
        foreach (['mode', 'xPaddingBytes', 'uplinkHTTPMethod', 'sessionIDPlacement', 'sessionIDKey',
                     'sessionIDTable', 'sessionIDLength', 'seqPlacement', 'seqKey', 'uplinkDataPlacement',
                     'uplinkDataKey', 'scMaxEachPostBytes', 'scMinPostsIntervalMs'] as $key) {
            if (isset($xhttp[$key]) && trim((string)$xhttp[$key]) !== '') {
                $extra[$key] = $xhttp[$key];
            }
        }
        foreach (['scMaxEachPostBytes' => '1000000', 'scMinPostsIntervalMs' => '30'] as $key => $default) {
            if (($extra[$key] ?? null) === $default) {
                unset($extra[$key]);
            }
        }
        foreach (['sessionPlacement' => 'sessionIDPlacement', 'sessionKey' => 'sessionIDKey'] as $legacy => $current) {
            if (!isset($extra[$current]) && isset($xhttp[$legacy]) && trim((string)$xhttp[$legacy]) !== '') {
                $extra[$current] = $xhttp[$legacy];
            }
        }
        foreach (['uplinkChunkSize'] as $key) {
            if (isset($xhttp[$key]) && (float)$xhttp[$key] !== 0.0) {
                $extra[$key] = $xhttp[$key];
            }
        }
        if (!empty($xhttp['noGRPCHeader'])) {
            $extra['noGRPCHeader'] = true;
        }
        foreach (['xmux', 'downloadSettings'] as $key) {
            if (is_array($xhttp[$key] ?? null) && $xhttp[$key] !== []) {
                $extra[$key] = $xhttp[$key];
            }
        }
        if (!empty($xhttp['xPaddingObfsMode'])) {
            $extra['xPaddingObfsMode'] = true;
            foreach (['xPaddingKey', 'xPaddingHeader', 'xPaddingPlacement', 'xPaddingMethod'] as $key) {
                if (isset($xhttp[$key]) && trim((string)$xhttp[$key]) !== '') {
                    $extra[$key] = $xhttp[$key];
                }
            }
        }
        if (is_array($xhttp['headers'] ?? null)) {
            $headers = [];
            foreach ($xhttp['headers'] as $key => $value) {
                if (strtolower((string)$key) !== 'host') {
                    $headers[$key] = $value;
                }
            }
            if ($headers !== []) {
                $extra['headers'] = $headers;
            }
        }

        return $extra;
    }

    private function displayName(array $node, array $settings): string
    {
        return ($this->nameRenderer ?? new VpnServerNameRenderer())->render($node, $settings);
    }

    private function serverHost(string $panelUrl): string
    {
        $host = parse_url(trim($panelUrl), PHP_URL_HOST);

        return is_string($host) ? trim($host, '[]') : '';
    }

    private function hostForUri(string $host): string
    {
        return str_contains($host, ':') ? '[' . trim($host, '[]') . ']' : $host;
    }

    private function headerHost(mixed $headers): string
    {
        if (!is_array($headers)) {
            return '';
        }
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === 'host') {
                return $this->firstValue([$value]);
            }
        }

        return '';
    }

    private function firstValue(array $values): string
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                if (!array_is_list($value)) {
                    continue;
                }
                $value = $this->firstValue($value);
            }
            $value = trim((string)$value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function filterParams(array $params): array
    {
        return array_filter($params, static fn(mixed $value): bool => $value !== null && $value !== '');
    }

    private function json(string $value): array
    {
        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
