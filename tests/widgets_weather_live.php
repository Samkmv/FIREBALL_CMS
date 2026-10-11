<?php
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/widgets_bootstrap.php';
$app->db = new \FBL\Database();
$app->set('lang', LANGS['ru']);
$weather = new \App\Services\WeatherService();
$data = $weather->get('Berlin', 'celsius', 3);
$again = (new \App\Services\WeatherService())->get('Berlin', 'celsius', 3);
$coordinates = $weather->getByCoordinates(52.52, 13.41, 'celsius', 3); // Synthetic public city centre, not a device location.
$coordinateAgain = (new \App\Services\WeatherService())->getByCoordinates(52.52, 13.41, 'celsius', 3);
$result = ['available' => $data !== null, 'city' => $data['city'] ?? null, 'forecast_days' => count($data['daily'] ?? []), 'cache_reused' => $data !== null && $again === $data,
    'coordinate_forecast_available' => $coordinates !== null, 'coordinate_cache_reused' => $coordinates !== null && $coordinateAgain === $coordinates];
file_put_contents(ROOT . '/tests/artifacts/widgets/weather-live.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($result['available'] && $result['cache_reused'] && $result['coordinate_forecast_available'] && $result['coordinate_cache_reused'] ? 0 : 1);
