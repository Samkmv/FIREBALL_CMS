<?php

namespace Fireball\VpnManagerV2\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QrCodeService
{
    private const DEFAULT_TTL = 3600;

    public function __construct(private readonly ?VpnSubscriptionUrlService $urls = null)
    {
    }

    public function renderForToken(string $token): string
    {
        try {
            $url = ($this->urls ?? new VpnSubscriptionUrlService())->forToken($token);
            $key = $this->cacheKey($token);
            $svg = cache()->get($key);
            if (!is_string($svg) || !str_contains($svg, '<svg')) {
                $renderer = new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd());
                $svg = (new Writer($renderer))->writeString($url);
                cache()->set($key, $svg, $this->ttl());
            }
        } catch (\Throwable $exception) {
            error_log('VPN Manager V2 QR generation failed: ' . get_class($exception));

            return '<div class="alert alert-warning mb-0">'
                . htmlSC(\FireballPluginVpnManagerV2::t('vpn_manager_v2_qr_unavailable'))
                . '</div>';
        }

        return '<div class="vpn-v2-qr-code border rounded-4 bg-white p-3 d-inline-flex">'
            . $svg
            . '</div>';
    }

    public function invalidateToken(string $token): void
    {
        cache()->remove($this->cacheKey($token));
    }

    public function cacheKey(string $token): string
    {
        return 'vpn-v2:qr:' . hash('sha256', strtolower(trim($token)));
    }

    public function ttl(): int
    {
        $settings = (new SettingsService())->current();

        return max(60, min(86400, (int)($settings['qr_cache_ttl_seconds'] ?? self::DEFAULT_TTL)));
    }
}
