<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\ServerData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Support\NetworkTargetGuard;

final class ServerValidator
{
    public function __construct(
        private readonly ?PanelUrlNormalizer $urlNormalizer = null,
        private readonly ?NetworkTargetGuard $networkGuard = null,
    ) {
    }

    public function validate(array $input): ServerData
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_name_required'));
        }

        $code = make_slug((string)($input['code'] ?? $name), 'vpn-server');
        if ($code === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_code_required'));
        }

        [$panelUrl, $panelPath] = ($this->urlNormalizer ?? new PanelUrlNormalizer())->normalize(
            (string)($input['panel_url'] ?? ''),
            (string)($input['panel_path'] ?? '')
        );
        $apiUrl = null;
        if (trim((string)($input['api_url'] ?? '')) !== '') {
            [$apiBase, $apiPath] = ($this->urlNormalizer ?? new PanelUrlNormalizer())->normalize(
                (string)$input['api_url']
            );
            $apiUrl = $apiBase . $apiPath;
        }

        $authType = (string)($input['auth_type'] ?? 'token');
        if (!in_array($authType, ['token', 'password'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_auth_type'));
        }

        $countryCode = strtoupper(trim((string)($input['country_code'] ?? '')));
        if ($countryCode !== '' && preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_country_code'));
        }
        $isEnabled = !empty($input['is_enabled']);
        if ($isEnabled && $countryCode === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_country_required'));
        }
        $allowPrivateNetwork = !empty($input['allow_private_network']);
        $panelHost = (string)(parse_url($panelUrl, PHP_URL_HOST) ?: '');
        ($this->networkGuard ?? new NetworkTargetGuard())->assertConfigurationHost($panelHost, $allowPrivateNetwork);
        if ($apiUrl !== null) {
            ($this->networkGuard ?? new NetworkTargetGuard())->assertConfigurationHost(
                (string)(parse_url($apiUrl, PHP_URL_HOST) ?: ''),
                $allowPrivateNetwork
            );
        }

        $connectTimeout = max(1, min(30, (int)($input['connect_timeout'] ?? 5)));
        $readTimeout = max(2, min(90, (int)($input['read_timeout'] ?? 15)));

        return new ServerData(
            name: mb_substr($name, 0, 255),
            code: mb_substr($code, 0, 80),
            panelUrl: mb_substr($panelUrl, 0, 500),
            panelPath: mb_substr($panelPath, 0, 190),
            apiUrl: $apiUrl !== null ? mb_substr($apiUrl, 0, 500) : null,
            authType: $authType,
            countryCode: $countryCode !== '' ? $countryCode : null,
            countryName: $this->nullableString($input['country_name'] ?? null, 120),
            city: $this->nullableString($input['city'] ?? null, 120),
            showFlag: !empty($input['show_flag']),
            isEnabled: $isEnabled,
            maintenanceMode: !empty($input['maintenance_mode']),
            allowNewConnections: !array_key_exists('allow_new_connections', $input) || !empty($input['allow_new_connections']),
            verifySsl: !array_key_exists('verify_ssl', $input) || !empty($input['verify_ssl']),
            allowPrivateNetwork: $allowPrivateNetwork,
            connectTimeout: $connectTimeout,
            readTimeout: $readTimeout,
        );
    }

    private function nullableString(mixed $value, int $length): ?string
    {
        $value = mb_substr(trim((string)$value), 0, $length);

        return $value !== '' ? $value : null;
    }
}
