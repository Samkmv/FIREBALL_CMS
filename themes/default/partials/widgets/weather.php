<?php
use App\Services\WeatherService;
$unit = $settings['unit'] === 'fahrenheit' ? '°F' : '°C';
$number = static fn($value): string => (string)round((float)$value);
$geolocation = ($settings['location'] ?? 'city') === 'geolocation' && !empty($context->instance['id']) && empty($personalized);
?>
<section class="card border rounded-4 mt-4" aria-label="<?= htmlSC(return_translation('widgets_weather')) ?>"
    <?php if ($geolocation): ?>data-weather-geolocation data-weather-url="<?= htmlSC(base_href('/widgets/weather/' . $context->instance['id'])) ?>"
    data-weather-page="<?= htmlSC($context->page) ?>" data-weather-token="<?= htmlSC(session()->get('needCSRFToken')) ?>"
    data-weather-loading="<?= htmlSC(return_translation('widgets_location_loading')) ?>" data-weather-fallback="<?= htmlSC(return_translation('widgets_location_failed')) ?>"
    data-weather-error="<?= htmlSC(return_translation('widgets_location_weather_failed')) ?>"<?php endif; ?>>
    <div class="card-body p-4">
        <div data-weather-body>
        <h4 class="h6 mb-3"><?= htmlSC($settings['title'] ?: return_translation('widgets_weather')) ?></h4>
        <div class="fw-semibold mb-2"><?= htmlSC($weather['city'] ?? $settings['city']) ?></div>
        <?php if ($weather === null): ?>
            <p class="text-body-secondary small mb-0"><?= htmlSC(return_translation('widgets_weather_unavailable')) ?></p>
        <?php else: ?>
            <?php [$icon, $condition] = WeatherService::condition((int)$weather['current']['weather_code'], (bool)($weather['current']['is_day'] ?? true)); ?>
            <div class="d-flex align-items-center gap-3 mb-2"><?= $context->partial('weather-icon', ['icon' => $icon, 'size' => 44]) ?><span class="display-6 mb-0"><?= htmlSC($number($weather['current']['temperature_2m']) . $unit) ?></span></div>
            <p class="mb-1"><?= htmlSC(return_translation($condition)) ?></p>
            <p class="text-body-secondary small mb-3"><?= htmlSC(return_translation('widgets_feels_like')) ?> <?= htmlSC($number($weather['current']['apparent_temperature']) . $unit) ?></p>
            <div class="d-flex flex-wrap gap-3 small text-body-secondary">
                <?php if ($settings['humidity']): ?><span><?= htmlSC(return_translation('widgets_humidity')) ?>: <?= (int)$weather['current']['relative_humidity_2m'] ?>%</span><?php endif; ?>
                <?php if ($settings['wind']): ?><span><?= htmlSC(return_translation('widgets_wind')) ?>: <?= htmlSC((string)round((float)$weather['current']['wind_speed_10m'], 1)) ?> <?= htmlSC(return_translation('widgets_wind_unit')) ?></span><?php endif; ?>
            </div>
            <?php if ($settings['forecast']): ?>
            <div class="mt-3 border-top pt-3 vstack gap-2">
                <?php foreach ($weather['daily'] as $day): ?>
                    <?php [$dailyIcon, $dailyCondition] = WeatherService::condition($day['code']); ?>
                    <div class="d-grid align-items-center gap-2 small" style="grid-template-columns: minmax(0, 1fr) 1.5rem minmax(0, 1fr)">
                        <time datetime="<?= htmlSC($day['date']) ?>"><?= htmlSC(date('d.m', strtotime($day['date']))) ?></time>
                        <span class="d-inline-flex align-items-center justify-content-center" title="<?= htmlSC(return_translation($dailyCondition)) ?>" aria-label="<?= htmlSC(return_translation($dailyCondition)) ?>"><?= $context->partial('weather-icon', ['icon' => $dailyIcon, 'size' => 24]) ?></span>
                        <span class="text-end text-nowrap"><?= htmlSC($number($day['max']) . '° / ' . $number($day['min']) . '°') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($weather['stale'])): ?><p class="small text-body-secondary mt-3 mb-0"><?= htmlSC(return_translation('widgets_weather_stale')) ?></p><?php endif; ?>
        <?php endif; ?>
        <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" class="d-inline-block text-body-tertiary mt-3 fs-xs">Open-Meteo</a>
        </div>
        <?php if ($geolocation): ?>
        <div class="mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-weather-locate><i class="ci-map-pin me-1" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_use_my_location')) ?></button>
            <p class="small text-body-secondary mt-2 mb-0"><?= htmlSC(return_translation('widgets_location_consent')) ?></p>
            <p class="small text-body-secondary mt-2 mb-0" data-weather-status role="status"></p>
        </div>
        <?php endif; ?>
    </div>
</section>
