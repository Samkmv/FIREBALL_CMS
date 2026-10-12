<?php
if (PHP_SAPI !== 'cli') exit(1);
$directory = realpath((string)getenv('WIDGET_TEST_DIR'));
if (!$directory || !str_starts_with($directory, dirname(__DIR__) . '/tests/.tmp/widgets-')) exit(1);
$base = 'http://127.0.0.1:' . (getenv('WIDGET_TEST_PORT') ?: '8093');
$cookie = $directory . '/http-check.cookies';
if (is_file($cookie)) unlink($cookie);
$checks = [];
function http_widget_request(string $path, ?array $payload = null, ?string $token = null): array
{
    global $base, $cookie;
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false]);
    if ($payload !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Requested-With: XMLHttpRequest', 'X-CSRF-Token: ' . ($token ?? '')]]);
    $body = curl_exec($curl);
    $code = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    curl_close($curl);
    return [$code, $body];
}
function http_widget_verify(bool $passed, string $description): void
{
    global $checks;
    if (!$passed) throw new RuntimeException('FAIL: ' . $description);
    $checks[] = $description;
}
try {
    [$code] = http_widget_request('/admin/widgets?test_role=guest');
    http_widget_verify($code === 302, 'Unauthenticated guest cannot open editor');
    [$code] = http_widget_request('/admin/widgets?test_role=user');
    http_widget_verify($code === 302, 'Ordinary user is redirected by the existing admin middleware');
    [$code, $profile] = http_widget_request('/profile');
    preg_match('/name="needCSRFToken" value="([^"]+)"/', $profile, $match);
    [$code] = http_widget_request('/admin/widgets/save', ['action' => 'add', 'type' => 'html', 'area' => 'posts.sidebar', 'revision' => 1], $match[1] ?? '');
    http_widget_verify($code === 302, 'Ordinary user with valid CSRF cannot mutate widgets');
    [$code, $html] = http_widget_request('/admin/widgets?test_role=admin');
    http_widget_verify($code === 200 && str_contains($html, 'widget-editor'), 'Admin editor route renders successfully');
    preg_match('/name="needCSRFToken" value="([^"]+)"/', $html, $match); $token = $match[1];
    preg_match('/data-revision="(\d+)"/', $html, $match); $revision = (int)$match[1];
    preg_match('/data-widget-id="(\d+)"/', $html, $match); $system = (int)$match[1];
    [$code] = http_widget_request('/admin/widgets/save', ['action' => 'delete', 'id' => $system, 'revision' => $revision], 'wrong');
    http_widget_verify($code === 419, 'AJAX mutation with invalid CSRF receives HTTP 419');
    [$code] = http_widget_request('/admin/widgets/save', ['action' => 'delete', 'id' => $system, 'revision' => $revision], $token);
    http_widget_verify($code === 422, 'AJAX required widget deletion receives HTTP 422');
    [$code] = http_widget_request('/admin/widgets/save', ['action' => 'add', 'type' => 'posts.categories', 'area' => 'posts.sidebar', 'revision' => $revision], $token);
    http_widget_verify($code === 422, 'AJAX duplicate system widget rejected');
    [$code] = http_widget_request('/admin/widgets/save', ['action' => 'add', 'type' => 'html', 'area' => 'missing.area', 'revision' => $revision], $token);
    http_widget_verify($code === 422, 'AJAX unknown widget area rejected');
    [$code] = http_widget_request('/admin/widgets/save', ['action' => 'delete', 'id' => $system, 'revision' => $revision - 1], $token);
    http_widget_verify($code === 409, 'AJAX stale configuration receives HTTP 409');
    [$code] = http_widget_request('/admin/widgets/save', ['action' => 'reorder', 'layout' => [['id' => 'bad', 'area' => 'posts.sidebar']], 'revision' => $revision], $token);
    http_widget_verify($code === 422, 'AJAX malformed layout rejected');
    [$code, $body] = http_widget_request('/admin/widgets/save', ['action' => 'add', 'type' => 'html', 'area' => 'posts.sidebar', 'revision' => $revision], $token);
    $added = json_decode($body, true);
    http_widget_verify($code === 200 && $added['status'] === 'ok' && $added['revision'] === $revision + 1, 'AJAX optional widget creation succeeds and advances revision');
    preg_match_all('/data-widget-id="(\d+)"/', $added['html'], $matches);
    $optionalId = max(array_map('intval', $matches[1]));
    [$code, $body] = http_widget_request('/admin/widgets/save', ['action' => 'delete', 'id' => $optionalId, 'revision' => $added['revision']], $token);
    $deleted = json_decode($body, true);
    http_widget_verify($code === 200 && $deleted['status'] === 'ok' && !str_contains($deleted['html'], 'data-widget-id="' . $optionalId . '"'), 'AJAX optional widget removal succeeds');
    [$code, $body] = http_widget_request('/admin/widgets/save', ['action' => 'add', 'type' => 'weather', 'area' => 'posts.sidebar', 'revision' => $deleted['revision']], $token);
    $createdWeather = json_decode($body, true);
    http_widget_verify($code === 200 && $createdWeather['status'] === 'ok', 'Weather instance is created through the real editor endpoint');
    preg_match_all('/data-widget-id="(\d+)"/', $createdWeather['html'], $matches); $weatherId = max(array_map('intval', $matches[1]));
    $coordinates = ['latitude' => 56.12, 'longitude' => 40.57, 'page' => 'posts.index'];
    [$code] = http_widget_request('/widgets/weather/' . $weatherId, $coordinates, $token);
    http_widget_verify($code === 404, 'Coordinate endpoint is unavailable for a fixed-city widget');
    [$code, $body] = http_widget_request('/admin/widgets/save', ['action' => 'update', 'id' => $weatherId, 'settings' => ['location' => 'geolocation'], 'enabled' => true, 'visibility' => ['posts.index' => true, 'posts.show' => false], 'revision' => $createdWeather['revision']], $token);
    $configuredWeather = json_decode($body, true);
    http_widget_verify($code === 200 && $configuredWeather['status'] === 'ok', 'Automatic geolocation mode persists through editor settings');
    [$code, $html] = http_widget_request('/posts?test_role=guest');
    preg_match('/data-weather-token="([^"]+)"/', $html, $match); $guestToken = $match[1] ?? '';
    http_widget_verify($code === 200 && $guestToken !== '' && str_contains($html, 'data-weather-geolocation') && str_contains($html, 'widgets-weather.js'), 'Anonymous public page provides opt-in controls and weather client script');
    [$code] = http_widget_request('/widgets/weather/' . $weatherId, $coordinates, 'wrong');
    http_widget_verify($code === 419, 'Personalized weather endpoint requires valid CSRF');
    [$code] = http_widget_request('/widgets/weather/' . $weatherId, ['latitude' => 91, 'longitude' => 40, 'page' => 'posts.index'], $guestToken);
    http_widget_verify($code === 422, 'Personalized weather endpoint rejects invalid coordinates');
    [$code, $body] = http_widget_request('/widgets/weather/' . $weatherId, $coordinates, $guestToken);
    $localWeather = json_decode($body, true);
    http_widget_verify($code === 200 && $localWeather['status'] === 'ok' && str_contains($localWeather['html'], 'Ваше местоположение') && str_contains($localWeather['html'], 'ci-cloud'), 'Anonymous personalized weather returns a rendered forecast with theme icons');
    [$code] = http_widget_request('/widgets/weather/' . $weatherId, ['latitude' => 56.12, 'longitude' => 40.57, 'page' => 'posts.show'], $guestToken);
    http_widget_verify($code === 404, 'Personalized weather honors per-page widget visibility');
    [$code, $html] = http_widget_request('/admin/widgets?test_role=admin');
    preg_match('/name="needCSRFToken" value="([^"]+)"/', $html, $match); $token = $match[1];
    http_widget_request('/admin/widgets/save', ['action' => 'delete', 'id' => $weatherId, 'revision' => $configuredWeather['revision']], $token);
    [$code, $html] = http_widget_request('/posts'); http_widget_verify($code === 200 && substr_count($html, 'id="blogSidebar"') === 1, 'Posts listing contains one responsive widget area');
    [$code, $html] = http_widget_request('/posts/first'); http_widget_verify($code === 200 && substr_count($html, 'id="blogSidebar"') === 1, 'Single post contains one responsive widget area');
    file_put_contents(dirname(__DIR__) . '/tests/artifacts/widgets/http-tests.json', json_encode(['passed' => count($checks), 'checks' => $checks], JSON_PRETTY_PRINT));
    echo count($checks) . " HTTP widget checks passed.\n";
} catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . "\n"); exit(1); }
