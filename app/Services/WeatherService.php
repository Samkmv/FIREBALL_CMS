<?php

namespace App\Services;

/** Open-Meteo only: fixed HTTPS hosts, bounded responses, short timeouts and shared CMS cache. */
final class WeatherService
{
    private array $memo = [];
    private ?\Closure $transport;

    public function __construct(?callable $transport = null) { $this->transport = $transport === null ? null : \Closure::fromCallable($transport); }

    public function get(string $city, string $unit, int $days): ?array
    {
        $city = trim($city);
        if ($city === '' || mb_strlen($city) > 100 || !in_array($unit, ['celsius', 'fahrenheit'], true) || $days < 1 || $days > 7) return null;
        $locale = \FBL\Localization::currentLocale();
        $key = 'widgets:weather:v1:' . hash('sha256', json_encode([mb_strtolower($city), $locale, $unit, $days]));
        return $this->load($key, $unit, $days, fn() => $this->resolveCity($city, $locale));
    }

    public function getByCoordinates(float $latitude, float $longitude, string $unit, int $days): ?array
    {
        if (!is_finite($latitude) || !is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180
            || !in_array($unit, ['celsius', 'fahrenheit'], true) || $days < 1 || $days > 7) return null;
        // Weather does not need precise GPS coordinates; nearby visitors share a cache entry.
        $latitude = round($latitude, 2);
        $longitude = round($longitude, 2);
        $key = 'widgets:weather:geo:v1:' . hash('sha256', json_encode([$latitude, $longitude, \FBL\Localization::currentLocale(), $unit, $days]));
        return $this->load($key, $unit, $days, static fn() => [
            'latitude' => $latitude, 'longitude' => $longitude, 'name' => return_translation('widgets_your_location'),
        ], true);
    }

    private function resolveCity(string $city, string $locale): array
    {
        $locationKey = 'widgets:weather:city:' . hash('sha256', mb_strtolower($city) . ':' . $locale);
        $location = cache()->get($locationKey);
        if (!is_array($location)) {
            $geo = $this->request('https://geocoding-api.open-meteo.com/v1/search', ['name' => $city, 'count' => 1, 'language' => substr($locale, 0, 2), 'format' => 'json']);
            $location = $geo['results'][0] ?? null;
            if (!is_array($location) || !is_numeric($location['latitude'] ?? null) || !is_numeric($location['longitude'] ?? null)
                || abs((float)$location['latitude']) > 90 || abs((float)$location['longitude']) > 180) {
                throw new \RuntimeException('Weather city was not found.');
            }
            $location = array_intersect_key($location, array_flip(['name', 'latitude', 'longitude', 'country', 'admin1']));
            cache()->set($locationKey, $location, 2592000);
        }
        return $location;
    }

    private function load(string $key, string $unit, int $days, callable $resolveLocation, bool $privateLocation = false): ?array
    {
        if (array_key_exists($key, $this->memo)) return $this->memo[$key];
        $cached = cache()->get($key);
        if (is_array($cached)) return $this->memo[$key] = ($cached['data'] ?? null);
        $stale = cache()->get($key . ':stale');
        $stale = is_array($stale) ? $stale + ['stale' => true] : null;
        // Avoid parallel cold requests multiplying API traffic. Lock files belong to the normal CMS cache.
        $directory = CACHE . '/widgets';
        if (!is_dir($directory)) @mkdir($directory, 0755, true);
        $lock = @fopen($directory . '/weather-' . hash('sha256', $key) . '.lock', 'c');
        if ($lock !== false && !flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return $this->memo[$key] = $stale;
        }
        try {
            $cached = cache()->get($key);
            if (is_array($cached)) return $this->memo[$key] = ($cached['data'] ?? null);
            $location = $resolveLocation();
            $forecast = $this->request('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $location['latitude'], 'longitude' => $location['longitude'], 'timezone' => 'auto',
                'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,wind_speed_10m,is_day',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min', 'temperature_unit' => $unit,
                'wind_speed_unit' => 'ms', 'forecast_days' => $days,
            ]);
            $current = $forecast['current'] ?? [];
            foreach (['temperature_2m', 'relative_humidity_2m', 'apparent_temperature', 'weather_code', 'wind_speed_10m'] as $field) {
                if (!is_numeric($current[$field] ?? null)) throw new \RuntimeException('Invalid current weather response.');
            }
            $daily = $forecast['daily'] ?? [];
            $list = [];
            foreach (array_slice((array)($daily['time'] ?? []), 0, $days) as $index => $date) {
                if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                    || !is_numeric($daily['weather_code'][$index] ?? null)
                    || !is_numeric($daily['temperature_2m_max'][$index] ?? null)
                    || !is_numeric($daily['temperature_2m_min'][$index] ?? null)) throw new \RuntimeException('Invalid daily weather response.');
                $list[] = ['date' => $date, 'code' => (int)$daily['weather_code'][$index], 'max' => (float)$daily['temperature_2m_max'][$index], 'min' => (float)$daily['temperature_2m_min'][$index]];
            }
            if (count($list) !== $days) throw new \RuntimeException('Incomplete weather response.');
            $data = ['city' => (string)($location['name'] ?? ''), 'region' => (string)($location['admin1'] ?? ''), 'country' => (string)($location['country'] ?? ''),
                'current' => $current, 'daily' => $list, 'unit' => $unit, 'updated_at' => time()];
            cache()->set($key, ['data' => $data], 900);
            cache()->set($key . ':stale', $data, 21600);
            return $this->memo[$key] = $data;
        } catch (\Throwable $exception) {
            log_error_details('Weather provider unavailable', $privateLocation ? ['Mode' => 'Geolocation', 'Exception' => get_class($exception)] : [], $privateLocation ? null : $exception);
            // Negative caching also covers geocoding and prevents retries on every page load.
            cache()->set($key, ['data' => $stale], 120);
            return $this->memo[$key] = $stale;
        } finally {
            if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
        }
    }

    private function request(string $endpoint, array $params): array
    {
        if ($this->transport !== null) return ($this->transport)($endpoint, $params);
        if (!function_exists('curl_init')) throw new \RuntimeException('Weather requires cURL.');
        $curl = curl_init($endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        $body = '';
        try {
            curl_setopt_array($curl, [
                CURLOPT_CONNECTTIMEOUT_MS => 1000, CURLOPT_TIMEOUT_MS => 2500, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'FIREBALL-CMS-Widgets/1.0', CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 262144) return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $success = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($success === false || $status !== 200) throw new \RuntimeException('Weather HTTP request failed: ' . $status);
            $result = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($result) || !empty($result['error'])) throw new \RuntimeException('Invalid weather response.');
            return $result;
        } finally { curl_close($curl); }
    }

    public static function condition(int $code, bool $day = true): array
    {
        return match (true) {
            $code === 0 => [$day ? 'sun' : 'moon', 'widgets_weather_clear'],
            $code <= 3 && $code >= 1 => ['cloud', 'widgets_weather_cloudy'],
            in_array($code, [45, 48], true) => ['fog', 'widgets_weather_fog'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['drizzle', 'widgets_weather_drizzle'],
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => ['rain', 'widgets_weather_rain'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['snow', 'widgets_weather_snow'],
            in_array($code, [95, 96, 99], true) => ['storm', 'widgets_weather_storm'],
            default => ['thermometer', 'widgets_weather_unknown'],
        };
    }
}
