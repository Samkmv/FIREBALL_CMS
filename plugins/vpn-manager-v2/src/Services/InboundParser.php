<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\DTO\ParsedInbound;
use Fireball\VpnManagerV2\Exceptions\InboundParseException;

final class InboundParser
{
    public function __construct(
        private readonly ?StreamSettingsParser $streamParser = null,
        private readonly ?VpnFlowResolver $flowResolver = null,
    ) {
    }

    public function parse(array $inbound): ParsedInbound
    {
        $remoteId = trim((string)($inbound['id'] ?? $inbound['remote_inbound_id'] ?? ''));
        if ($remoteId === '' || mb_strlen($remoteId) > 80 || !ctype_digit($remoteId) || (int)$remoteId <= 0) {
            throw new InboundParseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_remote_id'));
        }

        $protocol = strtolower(trim((string)($inbound['protocol'] ?? '')));
        if ($protocol === '') {
            throw new InboundParseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_protocol'));
        }
        $protocol = mb_substr($protocol, 0, 40);

        $port = (int)($inbound['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            throw new InboundParseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_port'));
        }

        $remark = $this->nullableString($inbound['remark'] ?? null, 255);
        $name = $this->nullableString($inbound['name'] ?? null, 255)
            ?? $remark
            ?? mb_substr('Inbound ' . $remoteId, 0, 255);

        $streamProvided = array_key_exists('streamSettings', $inbound) || array_key_exists('stream_settings', $inbound);
        $rawStream = $inbound['streamSettings'] ?? $inbound['stream_settings'] ?? null;
        $stream = ($this->streamParser ?? new StreamSettingsParser())->parse($rawStream, $streamProvided);
        [$settings, $settingsValid] = $this->settings($inbound['settings'] ?? null, array_key_exists('settings', $inbound));

        $flowInput = [
            'protocol' => $protocol,
            'network' => $stream->network,
            'security' => $stream->security,
        ];
        $defaultFlow = ($this->flowResolver ?? new VpnFlowResolver())->resolveDefaultFlow($flowInput);
        $remoteEnabled = !array_key_exists('enable', $inbound) || filter_var($inbound['enable'], FILTER_VALIDATE_BOOL);
        $valid = $stream->valid && $settingsValid;
        $status = $valid ? ($remoteEnabled ? 'active' : 'disabled') : 'parse_error';

        return new ParsedInbound(
            $remoteId,
            $name,
            $remark,
            $protocol,
            $port,
            $stream->network,
            $stream->security,
            $defaultFlow,
            $this->encode($settings),
            $this->encode($stream->normalized),
            $status,
            $remoteEnabled,
            $stream,
        );
    }

    private function settings(mixed $value, bool $provided): array
    {
        if (is_array($value)) {
            $settings = $value;
        } elseif (!$provided || $value === null) {
            $settings = [];
        } elseif (is_string($value) && trim($value) !== '') {
            $settings = json_decode($value, true);
            if (!is_array($settings)) {
                return [[], false];
            }
        } else {
            return [[], false];
        }

        // Client credentials and client-specific Flow never belong to an inbound snapshot.
        unset($settings['clients']);

        return [$settings, true];
    }

    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new InboundParseException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_inbound_json_encode'));
        }

        return $json;
    }

    private function nullableString(mixed $value, int $length): ?string
    {
        $value = mb_substr(trim((string)$value), 0, $length);

        return $value !== '' ? $value : null;
    }
}
