<?php

namespace Fireball\VpnManagerV2\Services;

final class ConfigurationSnapshotService
{
    public function fromRemote(array $server, array $inbound, array $client, array $node): array
    {
        $settings = $this->json($inbound['settings'] ?? []);
        $rawStream = $inbound['streamSettings'] ?? $inbound['stream_settings'] ?? [];
        $stream = (new StreamSettingsParser())->parse($rawStream, true)->normalized;
        $protocol = strtolower(trim((string)($inbound['protocol'] ?? $node['protocol'] ?? '')));
        $network = strtolower(trim((string)($stream['network'] ?? $inbound['network'] ?? $node['network'] ?? 'tcp')));
        $security = strtolower(trim((string)($stream['security'] ?? $inbound['security'] ?? $node['security'] ?? 'none')));
        $credentials = new RemoteClientCredentialService();
        $remoteCredential = $credentials->remoteCredential($protocol, $client);
        $usesPassword = $credentials->usesPassword($protocol);
        // Inbound settings contain every remote client. They must never be copied
        // into a per-connection snapshot or exposed by the subscription endpoint.
        unset($settings['clients']);

        return $this->canonicalize([
            'node_id' => (int)($node['id'] ?? $node['node_id'] ?? 0),
            'subscription_id' => (int)($node['subscription_id'] ?? 0),
            'server_id' => (int)($server['id'] ?? $node['server_id'] ?? 0),
            'server_name' => (string)($server['name'] ?? $node['server_name'] ?? ''),
            'server_code' => (string)($server['code'] ?? $node['server_code'] ?? ''),
            'panel_url' => (string)($server['panel_url'] ?? $node['panel_url'] ?? ''),
            'country_code' => strtoupper((string)($server['country_code'] ?? $node['country_code'] ?? '')),
            'country_name' => (string)($server['country_name'] ?? $node['country_name'] ?? ''),
            'city' => (string)($server['city'] ?? $node['city'] ?? ''),
            'show_flag' => (int)($server['show_flag'] ?? $node['show_flag'] ?? 1),
            'inbound_id' => (int)($node['inbound_id'] ?? 0),
            'remote_inbound_id' => (string)($inbound['id'] ?? $inbound['remote_inbound_id'] ?? ''),
            'inbound_name' => (string)($inbound['remark'] ?? $inbound['name'] ?? $node['inbound_name'] ?? ''),
            'inbound_remark' => (string)($inbound['remark'] ?? ''),
            'protocol' => $protocol,
            'port' => (int)($inbound['port'] ?? 0),
            'network' => $network,
            'security' => $security !== '' ? $security : 'none',
            'settings_json' => $this->encode($settings),
            'stream_settings_json' => $this->encode($stream),
            'client_uuid' => $usesPassword
                ? trim((string)($node['client_uuid'] ?? ''))
                : $remoteCredential,
            'client_credential_hash' => $usesPassword && $remoteCredential !== ''
                ? hash('sha256', $remoteCredential)
                : null,
            'client_email' => (string)($client['email'] ?? $node['client_email'] ?? ''),
            'client_sub_id' => (string)($client['subId'] ?? $client['subid'] ?? $node['client_sub_id'] ?? ''),
            'flow' => $this->nullable($client['flow'] ?? $node['flow'] ?? null),
            'enable' => (bool)($client['enable'] ?? true),
        ]);
    }

    public function validate(array $snapshot): ?string
    {
        $protocol = strtolower(trim((string)($snapshot['protocol'] ?? '')));
        if (!in_array($protocol, ['vless', 'vmess', 'trojan'], true)) {
            return 'unsupported_protocol';
        }
        if (trim((string)($snapshot['client_uuid'] ?? '')) === ''
            || trim((string)($snapshot['panel_url'] ?? '')) === ''
            || (int)($snapshot['port'] ?? 0) <= 0) {
            return 'required_field_missing';
        }
        if ((new RemoteClientCredentialService())->usesPassword($protocol)
            && trim((string)($snapshot['client_credential_hash'] ?? '')) === '') {
            return 'required_field_missing';
        }

        $security = strtolower(trim((string)($snapshot['security'] ?? 'none')));
        if ($security === 'reality' && $protocol !== 'vless') {
            return 'unsupported_security';
        }
        if ($protocol === 'vless' && $security === 'reality') {
            $stream = $this->json($snapshot['stream_settings_json'] ?? []);
            $reality = is_array($stream['realitySettings'] ?? null) ? $stream['realitySettings'] : [];
            $settings = is_array($reality['settings'] ?? null) ? $reality['settings'] : [];
            $publicKey = trim((string)($settings['publicKey'] ?? $reality['publicKey'] ?? ''));
            $serverName = $this->firstValue([
                $settings['serverName'] ?? null,
                $settings['serverNames'] ?? null,
                $reality['serverName'] ?? null,
                $reality['serverNames'] ?? null,
            ]);
            if ($publicKey === '' || $serverName === '') {
                return 'reality_required_field_missing';
            }
        }

        return null;
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', $this->encode($this->canonicalize($snapshot)));
    }

    public function changedFields(array $before, array $after): array
    {
        $fields = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $fields[] = (string)$key;
            }
        }

        return $fields;
    }

    public function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(fn(mixed $child): mixed => is_array($child) ? $this->canonicalize($child) : $child, $item)
                    : $this->canonicalize($item);
            }
        }
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function firstValue(array $values): string
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $nested = $this->firstValue($value);
                if ($nested !== '') {
                    return $nested;
                }
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $value = trim((string)$value);
            if ($value !== '' && $value !== '[redacted]') {
                return $value;
            }
        }

        return '';
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }
}
