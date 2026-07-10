<?php

namespace Fireball\VpnManager\Services;

final class QrCodeService
{
    public function renderPlaceholder(string $value): string
    {
        if ($value === '') {
            return '<div class="border rounded-4 p-4 text-center text-body-secondary">QR</div>';
        }

        return '<div class="border rounded-4 p-4 text-center text-body-secondary" data-vpn-qr-placeholder="' . htmlSC($value) . '">QR</div>';
    }
}
