<?php

namespace App\Controllers;

use App\Widgets\WidgetContext;
use App\Widgets\WidgetConflictException;

final class WidgetsController extends BaseController
{
    public function index(): string
    {
        if (!check_admin()) abort('', 403);
        $error = '';
        try {
            widgets()->instances->initialize();
            $snapshot = widgets()->instances->snapshot();
        } catch (\Throwable $exception) {
            log_error_details('Widget editor initialization failed', [], $exception);
            $error = return_translation('widgets_migration_required');
            $snapshot = ['revision' => 0, 'instances' => []];
        }
        return view('admin/widgets', [
            'title' => return_translation('admin_nav_widgets'), 'snapshot' => $snapshot, 'editor_error' => $error,
            'widget_context' => new WidgetContext('admin'),
            'styles' => [asset_versioned_url(base_url('/assets/default/css/admin-widgets.css'), WWW . '/assets/default/css/admin-widgets.css')],
            'footer_scripts' => [asset_versioned_url(base_url('/assets/default/js/admin-widgets.js'), WWW . '/assets/default/js/admin-widgets.js')],
        ]);
    }

    public function save(): void
    {
        // Defense in depth for direct controller use; routes also enforce auth/admin and CSRF.
        if (!check_admin()) response()->json(['status' => 'error', 'message' => return_translation('tpl_auth_required_admin')], 403);
        if (!app()->router->checkCSRFToken()) response()->json(['status' => 'error', 'message' => return_translation('tpl_security_error')], 419);
        $payload = request()->json();
        if (strlen(json_encode($payload)) > 200000 || !is_int($payload['revision'] ?? null)) {
            response()->json(['status' => 'error', 'message' => return_translation('widgets_invalid_request')], 422);
        }
        $repository = widgets()->instances;
        $context = new WidgetContext('admin');
        try {
            switch ($payload['action'] ?? '') {
                case 'add':
                    if (!is_string($payload['type'] ?? null) || !is_string($payload['area'] ?? null)) throw new \InvalidArgumentException(return_translation('widgets_invalid_request'));
                    $repository->add($payload['type'], $payload['area'], $payload['revision'], $context);
                    break;
                case 'update':
                    if (!is_int($payload['id'] ?? null) || !is_array($payload['settings'] ?? null) || !is_array($payload['visibility'] ?? null)) throw new \InvalidArgumentException(return_translation('widgets_invalid_request'));
                    $repository->update($payload['id'], $payload['settings'], $payload['enabled'] ?? null, $payload['visibility'], $payload['revision'], $context);
                    break;
                case 'delete':
                    if (!is_int($payload['id'] ?? null)) throw new \InvalidArgumentException(return_translation('widgets_invalid_request'));
                    $repository->delete($payload['id'], $payload['revision']);
                    break;
                case 'reorder':
                    if (!is_array($payload['layout'] ?? null)) throw new \InvalidArgumentException(return_translation('widgets_invalid_request'));
                    $repository->reorder($payload['layout'], $payload['revision']);
                    break;
                default: throw new \InvalidArgumentException(return_translation('widgets_invalid_request'));
            }
            $snapshot = $repository->snapshot();
            response()->json(['status' => 'ok', 'revision' => $snapshot['revision'], 'message' => return_translation('widgets_saved'),
                'html' => view()->renderPartial('admin/widgets_editor', ['snapshot' => $snapshot, 'widget_context' => $context])]);
        } catch (WidgetConflictException $exception) {
            response()->json(['status' => 'error', 'message' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            log_error_details('Widget editor save failed', [], $exception);
            response()->json(['status' => 'error', 'message' => return_translation('widgets_save_error')], 500);
        }
    }

    /** Public, CSRF-protected weather lookup; browser coordinates never become saved settings. */
    public function weather(): void
    {
        header('Cache-Control: private, no-store');
        $payload = request()->json();
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        if ((!is_int($latitude) && !is_float($latitude)) || (!is_int($longitude) && !is_float($longitude))
            || !is_finite((float)$latitude) || !is_finite((float)$longitude) || abs($latitude) > 90 || abs($longitude) > 180
            || !is_string($payload['page'] ?? null)) {
            response()->json(['status' => 'error', 'message' => return_translation('widgets_invalid_request')], 422);
        }
        try {
            $manager = widgets();
            $id = (int)get_route_param('id');
            $instance = array_column($manager->instances->snapshot()['instances'], null, 'id')[$id] ?? null;
            $area = $instance !== null ? $manager->areas->get($instance['area']) : null;
            if ($instance === null || $instance['type'] !== 'weather' || !$instance['enabled'] || $area === null
                || !isset($area['pages'][$payload['page']]) || empty($instance['visibility'][$payload['page']])) {
                response()->json(['status' => 'error', 'message' => return_translation('widgets_weather_unavailable')], 404);
            }
            $context = new WidgetContext($payload['page'], [], 'responsive', $instance);
            $settings = $manager->types->get('weather')['widget']->validate($instance['settings'], $context);
            if ($settings['location'] !== 'geolocation') {
                response()->json(['status' => 'error', 'message' => return_translation('widgets_weather_unavailable')], 404);
            }
            if (!\FBL\RateLimiter::attempt('widgets-weather-ip:' . client_ip(), 30, 60)
                || !\FBL\RateLimiter::attempt('widgets-weather-global', 300, 60)) {
                response()->json(['status' => 'error', 'message' => return_translation('widgets_location_weather_failed')], 429);
            }
            $service = app()->get('widgets.weather');
            if (!$service instanceof \App\Services\WeatherService) {
                $service = new \App\Services\WeatherService();
                app()->set('widgets.weather', $service);
            }
            $weather = $service->getByCoordinates((float)$latitude, (float)$longitude, $settings['unit'], $settings['days']);
            if ($weather === null) response()->json(['status' => 'error', 'message' => return_translation('widgets_location_weather_failed')], 503);
            response()->json(['status' => 'ok', 'html' => $context->partial('weather', compact('settings', 'weather') + ['personalized' => true])]);
        } catch (\Throwable $exception) {
            // Do not include browser coordinates in application log messages.
            log_error_details('Personalized weather lookup failed', ['Exception' => get_class($exception)]);
            response()->json(['status' => 'error', 'message' => return_translation('widgets_location_weather_failed')], 503);
        }
    }
}
