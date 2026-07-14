<?php

namespace Fireball\VpnManager\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QrCodeService
{
    public function render(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !class_exists(Writer::class)) {
            return $this->unavailable();
        }

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(240, 2),
                new SvgImageBackEnd()
            );
            $svg = (new Writer($renderer))->writeString($value);
        } catch (\Throwable $exception) {
            log_error_details('VPN Manager QR generation failed', [], $exception);

            return $this->unavailable();
        }

        return '<div class="vpn-qr-code border rounded-4 bg-white p-3 d-inline-flex">' . $svg . '</div>';
    }

    public function renderPlaceholder(string $value): string
    {
        return $this->render($value);
    }

    private function unavailable(): string
    {
        return '<div class="alert alert-warning mb-0">' . htmlSC(\FireballPluginVpnManager::t('vpn_manager_qr_create_failed')) . '</div>';
    }
}
