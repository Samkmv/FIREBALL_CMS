<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
require dirname(__DIR__) . '/Plugin.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$tableCards = (string)file_get_contents(
    ROOT . '/app/Views/themes/default/admin/partials/responsive_table_cards.php'
);
$mainJs = (string)file_get_contents(ROOT . '/public/assets/default/js/main.js');
$publicRoutes = (string)file_get_contents(dirname(__DIR__) . '/routes/public.php');
$oldRoutes = (string)file_get_contents(ROOT . '/plugins/vpn-manager/routes.php');
$serverForm = (string)file_get_contents(dirname(__DIR__) . '/views/admin/server-form.php');

$assert(str_contains($tableCards, 'data-admin-post-actions-dropdown'),
    'The universal mobile table dropdown is missing its floating-menu contract.');
$assert(str_contains($mainJs, "document.addEventListener('scroll', state.update")
    && str_contains($mainJs, "menu.style.position = 'fixed'")
    && str_contains($mainJs, 'getBoundingClientRect()'),
    'The universal dropdown does not follow its trigger while a scroll container moves.');
$assert(str_contains($publicRoutes, '/vpn-v2/subscription/')
    && !str_contains($oldRoutes, '/vpn-v2/subscription/'),
    'The V2 public subscription route conflicts with the old plugin.');
$assert(substr_count($serverForm, 'autocomplete="new-password" value=""') === 3,
    'A server secret can be rendered back into HTML.');

$resolver = new Fireball\VpnManagerV2\Services\VpnFlowResolver();
$xhttp = ['protocol' => 'vless', 'network' => 'xhttp', 'security' => 'reality'];
$tcp = ['protocol' => 'vless', 'network' => 'tcp', 'security' => 'reality'];
$assert(!$resolver->isFlowCompatible('xtls-rprx-vision', $xhttp)
    && $resolver->isFlowCompatible('xtls-rprx-vision', $tcp),
    'The final acceptance flow policy is invalid.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'universal_dropdown_contract',
        'dropdown_scroll_tracking',
        'v1_v2_route_isolation',
        'secret_fields_empty_in_html',
        'tcp_xhttp_flow_separation',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
