<?php

use Fireball\Calendar\Jobs\DispatchRemindersJob;
use FBL\Plugins\PluginInterface;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\Calendar\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

final class FireballPluginCalendar implements PluginInterface
{
    public const SLUG = 'calendar';

    public function install(): void
    {
        fireball_event('calendar.installed', ['slug' => self::SLUG]);
    }

    public function uninstall(): void
    {
        // Calendar data is retained intentionally.
    }

    public function activate(): void
    {
        fireball_event('calendar.activated', ['slug' => self::SLUG]);
    }

    public function deactivate(): void
    {
        fireball_event('calendar.deactivated', ['slug' => self::SLUG]);
    }

    public function boot(): void
    {
        add_filter('admin_menu', static function (array $menu): array {
            $menu[] = [
                'group' => 'applications',
                'label' => self::t('calendar_menu'),
                'href' => base_href('/admin/calendar'),
                'icon' => 'ci-calendar',
                'plugin_menu' => true,
                'order' => 35,
            ];

            return $menu;
        });

        add_filter('fireball_scheduled_jobs', static function (array $jobs): array {
            $jobs['calendar_dispatch_reminders'] = [
                'class' => DispatchRemindersJob::class,
                'schedule' => '* * * * *',
                'plugin' => self::SLUG,
            ];

            return $jobs;
        });
    }

    public static function viewData(array $data = []): array
    {
        $css = __DIR__ . '/assets/calendar.css';
        $js = __DIR__ . '/assets/calendar.js';

        return array_merge([
            'styles' => [base_href('/plugins/calendar/assets/calendar.css?v=' . (is_file($css) ? filemtime($css) : time()))],
            'footer_scripts' => [base_href('/plugins/calendar/assets/calendar.js?v=' . (is_file($js) ? filemtime($js) : time()))],
        ], $data);
    }

    public static function t(string $key, array $replace = []): string
    {
        $value = \FBL\Language::get($key);
        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string)$replacement, $value);
        }

        return $value;
    }
}
