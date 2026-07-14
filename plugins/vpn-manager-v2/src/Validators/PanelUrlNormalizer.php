<?php

namespace Fireball\VpnManagerV2\Validators;

use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class PanelUrlNormalizer
{
    public function normalize(string $panelUrl, string $panelPath = ''): array
    {
        $panelUrl = trim($panelUrl);
        if ($panelUrl === '' || filter_var($panelUrl, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_panel_url'));
        }

        $parts = parse_url($panelUrl);
        if (!is_array($parts)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_panel_url'));
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_panel_url'));
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_panel_url'));
        }

        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_panel_url'));
        }

        $baseUrl = $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
        $path = $this->normalizePath((string)($parts['path'] ?? ''), $panelPath);

        return [$baseUrl, $path];
    }

    private function normalizePath(string $urlPath, string $panelPath): string
    {
        $combined = trim($urlPath, '/') . '/' . trim($panelPath, '/');
        $combined = trim((string)preg_replace('#/+#', '/', $combined), '/');
        if ($combined === '') {
            return '';
        }
        if (preg_match('/[\\\\?#\x00-\x1F\x7F]/', $combined) === 1) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_panel_path'));
        }

        foreach (explode('/', $combined) as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '.' || $decoded === '..') {
                throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_invalid_panel_path'));
            }
        }

        return '/' . $combined;
    }
}
