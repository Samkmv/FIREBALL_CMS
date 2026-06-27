<?php

/** @var \FBL\Router $router */

$router->get('/toy-car-rental', static function (): string {
    return plugin_view('toy-car-rental', 'frontend', [
        'title' => 'Toy Car Rental',
    ]);
});
