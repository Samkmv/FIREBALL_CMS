<?php
// Both application maintenance and the pre-bootstrap guard use this one presentation.
header('Cache-Control: no-store');
$maintenanceSiteTitle = trim((string)site_setting('site_title', SITE_NAME));
$maintenance = [
    'locale' => current_locale(),
    'site_title' => $maintenanceSiteTitle !== '' ? $maintenanceSiteTitle : SITE_NAME,
    'home_url' => base_href('/'),
    'retry_after' => max(5, (int)($retry_after ?? 12)),
    'copy' => array_map('return_translation', [
        'update_maintenance_title',
        'update_maintenance_status',
        'update_maintenance_message',
        'update_maintenance_hint',
        'update_maintenance_refresh',
    ]),
];
require dirname(__DIR__, 3) . '/system/update.php';
