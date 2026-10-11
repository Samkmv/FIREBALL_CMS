<?php

namespace App\Widgets\Types;

use App\Widgets\AbstractWidget;
use App\Widgets\WidgetContext;
use App\Services\WeatherService;

final class WeatherWidget extends AbstractWidget
{
    public function defaults(): array
    {
        return ['title' => '', 'location' => 'city', 'city' => 'Москва', 'unit' => 'celsius', 'days' => 3, 'humidity' => true, 'wind' => true, 'forecast' => true];
    }
    public function fields(WidgetContext $context): array
    {
        return [
            'title' => $this->titleField(),
            'location' => ['type' => 'select', 'label' => 'widgets_location_mode', 'wide' => true, 'options' => ['city' => return_translation('widgets_location_city'), 'geolocation' => return_translation('widgets_location_auto')]],
            'city' => ['type' => 'text', 'label' => 'widgets_city_fallback', 'max' => 100],
            'unit' => ['type' => 'select', 'label' => 'widgets_temperature_unit', 'options' => ['celsius' => '°C', 'fahrenheit' => '°F']],
            'days' => ['type' => 'number', 'label' => 'widgets_forecast_days', 'min' => 1, 'max' => 7],
            'humidity' => ['type' => 'checkbox', 'label' => 'widgets_humidity'], 'wind' => ['type' => 'checkbox', 'label' => 'widgets_wind'],
            'forecast' => ['type' => 'checkbox', 'label' => 'widgets_forecast'],
        ];
    }
    public function validate(array $settings, WidgetContext $context): array
    {
        $settings = parent::validate($settings, $context);
        if ($settings['city'] === '' || preg_match('/[\x00-\x1f]/', $settings['city'])) throw new \InvalidArgumentException(return_translation('widgets_invalid_setting') . ': ' . return_translation('widgets_city'));
        return $settings;
    }
    public function render(array $settings, WidgetContext $context): string
    {
        $service = app()->get('widgets.weather');
        if (!$service instanceof WeatherService) { $service = new WeatherService(); app()->set('widgets.weather', $service); }
        $weather = $service->get($settings['city'], $settings['unit'], $settings['days']);
        return $context->partial('weather', compact('settings', 'weather'));
    }
}
