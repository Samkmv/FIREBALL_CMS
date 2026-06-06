<?php

namespace App\Controllers\Api\V1;

use App\Models\Page;

/**
 * Returns cached public CMS page menus.
 */
class MenuController
{
    public function index(): void
    {
        $type = trim((string)get_route_param('type', ''));
        $supportedTypes = ['header', 'footer', 'legal_information'];

        if (!in_array($type, $supportedTypes, true)) {
            response()->json([
                'status' => 'error',
                'message' => 'Unsupported menu type.',
                'supported_types' => $supportedTypes,
            ], 404);
        }

        response()->json([
            'status' => 'success',
            'type' => $type,
            'data' => (new Page())->getMenuPages($type),
        ]);
    }
}
