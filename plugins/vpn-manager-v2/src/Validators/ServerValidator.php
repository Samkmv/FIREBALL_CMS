<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\DTO\ServerData;
use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class ServerValidator
{
    public function __construct(private readonly ?PanelUrlNormalizer $urlNormalizer = null)
    {
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

        $authType = (string)($input['auth_type'] ?? 'token');
        if (!in_array($authType, ['token', 'password'], true)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_auth_type'));
        }

        $countryCode = strtoupper(trim((string)($input['country_code'] ?? '')));
        if ($countryCode !== '' && preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_country_code'));
        }

        return new ServerData(
            name: mb_substr($name, 0, 255),
            code: mb_substr($code, 0, 80),
            panelUrl: mb_substr($panelUrl, 0, 500),
            panelPath: mb_substr($panelPath, 0, 190),
            authType: $authType,
            countryCode: $countryCode !== '' ? $countryCode : null,
            countryName: $this->nullableString($input['country_name'] ?? null, 120),
            city: $this->nullableString($input['city'] ?? null, 120),
            showFlag: !empty($input['show_flag']),
            isEnabled: !empty($input['is_enabled']),
        );
    }

    private function nullableString(mixed $value, int $length): ?string
    {
        $value = mb_substr(trim((string)$value), 0, $length);

        return $value !== '' ? $value : null;
    }
}
