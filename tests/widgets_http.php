<?php
/** Private browser fixture; run only with php -S 127.0.0.1 and WIDGET_TEST_DIR. */
if (PHP_SAPI !== 'cli-server' || !getenv('WIDGET_TEST_DIR')) { http_response_code(404); exit; }
$fixturePath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($fixturePath, '/assets/') && is_file(dirname(__DIR__) . '/public' . $fixturePath)) return false;
if (str_starts_with($fixturePath, '/themes/') && is_file(dirname(__DIR__) . $fixturePath)) {
    $asset = dirname(__DIR__) . $fixturePath;
    header('Content-Type: ' . match(pathinfo($asset, PATHINFO_EXTENSION)) { 'css' => 'text/css', 'js' => 'application/javascript', 'woff2' => 'font/woff2', 'png' => 'image/png', default => 'application/octet-stream' });
    readfile($asset); exit;
}
require __DIR__ . '/widgets_bootstrap.php';
$app->db = new \FBL\Database();
if (isset($_GET['test_role'])) {
    session()->remove('auth.session_registered');
    if ($_GET['test_role'] === 'guest') session()->remove('user');
    else {
        $user = db()->query('SELECT * FROM users WHERE login = ?', [$_GET['test_role'] === 'admin' ? 'widget-test' : 'widget-reader'])->getOne();
        \FBL\Auth::loginUser($user);
    }
}
// Weather is deterministic for layout checks; transport/cache/error behavior is checked in widgets.php.
$app->set('widgets.weather', new \App\Services\WeatherService(static fn($endpoint, $params) => str_contains($endpoint, 'geocoding')
    ? ['results' => [['name' => $params['name'], 'latitude' => 55.75, 'longitude' => 37.62]]]
    : ['current' => ['temperature_2m' => 10, 'relative_humidity_2m' => 70, 'apparent_temperature' => 8, 'weather_code' => 3, 'wind_speed_10m' => 2, 'is_day' => 1],
        'daily' => ['time' => ['2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'], 'weather_code' => [3, 0, 61, 3, 3, 3, 3], 'temperature_2m_max' => [12, 13, 11, 12, 12, 12, 12], 'temperature_2m_min' => [7, 6, 7, 7, 7, 7, 7]]]
));
$app->bootInstalledServices();
require CONFIG . '/routes.php';
$app->run();
