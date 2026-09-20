<?php
declare(strict_types=1);

// Execute the real plugin asset route without bootstrapping the CMS or database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../../../core/Request.php';
require_once __DIR__ . '/../../../../core/Response.php';
require_once __DIR__ . '/../../../../core/Router.php';
define('LANGS', ['ru' => [], 'en' => [], 'de' => [], 'zh-cn' => []]);
define('MULTILANGS', true);
function abort(): never { exit(44); }
function get_route_param(string $key): string { return $GLOBALS['router']->route_params[$key] ?? ''; }
$router = new FBL\Router(new FBL\Request('/'), new FBL\Response());
require __DIR__ . '/../../routes.php';
$path = (string)($argv[1] ?? '');
foreach ($router->getRoutes() as $route) {
    if (!$route['callback'] instanceof Closure || !preg_match($route['pattern'], $path, $matches)) continue;
    $router->route_params = $matches;
    ($route['callback'])();
}
abort();
