<?php

use App\Services\DatabaseMaintenanceService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$service = new DatabaseMaintenanceService();

return $service->run('full_reset', [
    'id' => 0,
    'name' => 'CLI',
], '127.0.0.1');
