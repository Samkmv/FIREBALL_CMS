<?php

namespace Fireball\VpnManagerV2\Controllers\Public;

use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use FBL\RateLimiter;

final class SubscriptionController
{
    public function show(): string
    {
        $format = (string)request()->get('format', 'base64');
        $xray = strtolower(trim($format)) === 'xray';
        if ($xray && !request_is_secure()) {
            http_response_code(403);
            header('Cache-Control: no-store');
            return '';
        }
        $remoteAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $rateLimit = $xray ? 'vpn-v2-xray:' : 'vpn-v2-subscription:';
        if (!RateLimiter::attempt($rateLimit . hash('sha256', $remoteAddress), 120, 60)) {
            http_response_code(429);
            header('Retry-After: 60');
            header('Cache-Control: no-store');

            return '';
        }
        $response = (new VpnSubscriptionEndpointService())->respond(
            (string)get_route_param('token'),
            $format,
            (string)request()->header('If-None-Match', ''),
            (string)request()->header('If-Modified-Since', ''),
            [
                // FIREBALL_VPN_HWID_GATEWAY_V1
                'X-HWID' => (string)request()->header('X-HWID', ''),
                'X-Device-OS' => (string)request()->header('X-Device-OS', ''),
                'X-Ver-OS' => (string)request()->header('X-Ver-OS', ''),
                'X-Device-Model' => (string)request()->header('X-Device-Model', ''),
                'X-Client' => (string)request()->header('X-Client', ''),
                'User-Agent' => (string)request()->header('User-Agent', ''),
            ]
        );
        http_response_code($response->status);
        foreach ($response->headers as $name => $value) {
            header($name . ': ' . str_replace(["\r", "\n"], '', (string)$value));
        }

        return $response->body;
    }
}
