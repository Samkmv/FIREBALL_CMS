<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ClientVerificationException;

final class ClientVerifier
{
    public function __construct(private readonly ?VpnFlowResolver $flowResolver = null)
    {
    }

    public function findInInbound(array $inbound, string $clientId, string $clientEmail): ?array
    {
        $settings = $inbound['settings'] ?? [];
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        foreach ((array)($settings['clients'] ?? []) as $client) {
            if (!is_array($client)) {
                continue;
            }

            $remoteId = trim((string)($client['id'] ?? $client['uuid'] ?? $client['password'] ?? ''));
            $remoteEmail = trim((string)($client['email'] ?? ''));
            if (($clientId !== '' && $remoteId !== '' && hash_equals($remoteId, $clientId))
                || ($clientEmail !== '' && $remoteEmail !== '' && hash_equals($remoteEmail, $clientEmail))) {
                return $client;
            }
        }

        return null;
    }

    public function verify(array $remoteClient, array $expectedPayload): void
    {
        $this->assertIdentity($remoteClient, $expectedPayload);
        $expectedId = trim((string)($expectedPayload['id'] ?? $expectedPayload['password'] ?? ''));
        $remoteId = trim((string)($remoteClient['id'] ?? $remoteClient['uuid'] ?? $remoteClient['password'] ?? ''));
        $expectedEmail = trim((string)($expectedPayload['email'] ?? ''));
        $remoteEmail = trim((string)($remoteClient['email'] ?? ''));

        $resolver = $this->flowResolver ?? new VpnFlowResolver();
        $expectedFlow = $resolver->normalizeFlow((string)($expectedPayload['flow'] ?? ''));
        $remoteFlow = $resolver->normalizeFlow((string)($remoteClient['flow'] ?? ''));
        if ($expectedFlow !== $remoteFlow) {
            throw new ClientVerificationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_flow_not_saved'),
                true
            );
        }

        $matches = $expectedId !== ''
            && $remoteId !== ''
            && hash_equals($expectedId, $remoteId)
            && $expectedEmail !== ''
            && $remoteEmail !== ''
            && hash_equals($expectedEmail, $remoteEmail)
            && $this->enabled($remoteClient['enable'] ?? false) === $this->enabled($expectedPayload['enable'] ?? false)
            && (int)($remoteClient['expiryTime'] ?? 0) === (int)($expectedPayload['expiryTime'] ?? 0)
            && (int)($remoteClient['totalGB'] ?? 0) === (int)($expectedPayload['totalGB'] ?? 0)
            && (int)($remoteClient['limitIp'] ?? 0) === (int)($expectedPayload['limitIp'] ?? 0);

        if (!$matches) {
            throw new ClientVerificationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_verification_failed')
            );
        }
    }

    public function assertIdentity(array $remoteClient, array $expectedPayload): void
    {
        $expectedId = trim((string)($expectedPayload['id'] ?? $expectedPayload['password'] ?? ''));
        $remoteId = trim((string)($remoteClient['id'] ?? $remoteClient['uuid'] ?? $remoteClient['password'] ?? ''));
        $expectedEmail = trim((string)($expectedPayload['email'] ?? ''));
        $remoteEmail = trim((string)($remoteClient['email'] ?? ''));
        if ($expectedId === '' || $remoteId === '' || !hash_equals($expectedId, $remoteId)
            || $expectedEmail === '' || $remoteEmail === '' || !hash_equals($expectedEmail, $remoteEmail)) {
            throw new ClientVerificationException(
                \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_identity_changed')
            );
        }
    }

    public function changedFields(array $remoteClient, array $expectedPayload): array
    {
        $changed = [];
        foreach (['expiryTime', 'totalGB', 'limitIp', 'enable', 'flow'] as $field) {
            if (!$this->sameField($field, $remoteClient[$field] ?? null, $expectedPayload[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    public function verifyFields(array $remoteClient, array $expectedPayload, array $fields): void
    {
        $this->assertIdentity($remoteClient, $expectedPayload);
        foreach ($fields as $field) {
            if (!$this->sameField((string)$field, $remoteClient[$field] ?? null, $expectedPayload[$field] ?? null)) {
                throw new ClientVerificationException(
                    (string)$field === 'flow'
                        ? \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_flow_not_saved')
                        : \FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_verification_failed'),
                    (string)$field === 'flow'
                );
            }
        }
    }

    private function sameField(string $field, mixed $actual, mixed $expected): bool
    {
        return match ($field) {
            'flow' => ($this->flowResolver ?? new VpnFlowResolver())->normalizeFlow((string)$actual)
                === ($this->flowResolver ?? new VpnFlowResolver())->normalizeFlow((string)$expected),
            'enable' => $this->enabled($actual) === $this->enabled($expected),
            'expiryTime', 'totalGB', 'limitIp' => (int)$actual === (int)$expected,
            default => (string)$actual === (string)$expected,
        };
    }

    private function enabled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int)$value === 1;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL) === true;
    }
}
