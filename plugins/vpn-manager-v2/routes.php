<?php

/** @var \FBL\Router $router */

$router->get('/plugins/vpn-manager-v2/assets/(?P<file>[a-z0-9._-]+)', static function (): never {
    $file = (string)get_route_param('file');
    $contentTypes = [
        'vpn-manager-v2.js' => 'application/javascript',
        'profile-vpn.css' => 'text/css',
    ];
    if (!isset($contentTypes[$file])) {
        abort();
    }

    $path = __DIR__ . '/assets/' . $file;
    $real = realpath($path);
    $base = realpath(__DIR__ . '/assets');
    if ($real === false || $base === false || !str_starts_with($real, rtrim($base, '/') . '/')) {
        abort();
    }

    header('Content-Type: ' . $contentTypes[$file] . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile($real);
    exit;
});

require __DIR__ . '/routes/public.php';
