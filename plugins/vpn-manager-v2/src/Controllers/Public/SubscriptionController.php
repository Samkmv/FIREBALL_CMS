<?php

namespace Fireball\VpnManagerV2\Controllers\Public;

use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;

final class SubscriptionController
{
    public function show(): string
    {
        $response = (new VpnSubscriptionEndpointService())->respond(
            (string)get_route_param('token'),
            (string)request()->get('format', 'base64'),
            (string)request()->header('If-None-Match', ''),
            (string)request()->header('If-Modified-Since', '')
        );
        http_response_code($response->status);
        foreach ($response->headers as $name => $value) {
            header($name . ': ' . str_replace(["\r", "\n"], '', (string)$value));
        }

        return $response->body;
    }
}
