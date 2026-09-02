<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Services/OccurrenceService.php';

use Fireball\Calendar\Services\OccurrenceService;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$timezone = new DateTimeZone('UTC');
$service = new OccurrenceService($timezone);
$range = static fn(string $value): DateTimeImmutable => new DateTimeImmutable($value, new DateTimeZone('UTC'));

$weekly = $service->between([
    'id' => 1,
    'starts_at' => '2026-09-01 18:00:00',
    'ends_at' => '2026-09-01 19:00:00',
    'recurrence' => 'weekly',
    'recurrence_until' => '2026-09-30 23:59:59',
], $range('2026-09-01'), $range('2026-10-01'));
$assert(count($weekly) === 5, 'Weekly recurrence should include five September occurrences.');
$assert(($weekly[1]['starts_at'] ?? '') === '2026-09-08 18:00:00', 'Weekly recurrence should keep its weekday and time.');

$monthly = $service->between([
    'id' => 2,
    'starts_at' => '2027-01-31 09:30:00',
    'ends_at' => '2027-01-31 10:30:00',
    'recurrence' => 'monthly',
    'recurrence_until' => '2027-04-30 23:59:59',
], $range('2027-01-01'), $range('2027-05-01'));
$assert(array_column($monthly, 'starts_at') === [
    '2027-01-31 09:30:00',
    '2027-02-28 09:30:00',
    '2027-03-31 09:30:00',
    '2027-04-30 09:30:00',
], 'Monthly recurrence should clamp to the final day of short months.');

$yearly = $service->between([
    'id' => 3,
    'starts_at' => '2024-02-29 12:00:00',
    'ends_at' => '2024-02-29 13:00:00',
    'recurrence' => 'yearly',
    'recurrence_until' => '2026-12-31 23:59:59',
], $range('2024-01-01'), $range('2027-01-01'));
$assert(($yearly[1]['starts_at'] ?? '') === '2025-02-28 12:00:00', 'Yearly leap-day recurrence should clamp in non-leap years.');

$zeroDuration = $service->between([
    'id' => 4,
    'starts_at' => '2026-09-10 09:00:00',
    'ends_at' => '2026-09-10 09:00:00',
    'recurrence' => 'none',
], $range('2026-09-10'), $range('2026-09-11'));
$assert($zeroDuration === [], 'Zero-duration events should not produce calendar occurrences.');

$dayReminder = $service->reminderAt($weekly[2], [
    'offset_value' => 3,
    'offset_unit' => 'days',
    'remind_time' => '10:00',
]);
$assert($dayReminder?->format('Y-m-d H:i:s') === '2026-09-12 10:00:00', 'Day reminders should honor the selected delivery time.');

$hourReminder = $service->reminderAt($weekly[2], [
    'offset_value' => 2,
    'offset_unit' => 'hours',
]);
$assert($hourReminder?->format('Y-m-d H:i:s') === '2026-09-15 16:00:00', 'Hour reminders should use an exact offset.');

$pluginRoot = dirname(__DIR__);
$manifest = json_decode((string)file_get_contents($pluginRoot . '/plugin.json'), true);
$assert(is_array($manifest) && ($manifest['slug'] ?? '') === 'calendar', 'Calendar plugin manifest should be valid.');
$assert(is_file($pluginRoot . '/migrations/001_create_calendar_tables.sql'), 'Calendar migration should exist.');
$assert(str_contains((string)file_get_contents($pluginRoot . '/Plugin.php'), 'fireball_scheduled_jobs'), 'Calendar should register its reminder job.');
$assert(str_contains((string)file_get_contents(dirname($pluginRoot, 2) . '/app/Services/NotificationService.php'), "push_notification"), 'Notification service should support site-only delivery.');
$routes = (string)file_get_contents($pluginRoot . '/routes.php');
$assert(str_contains($routes, "'/admin/calendar'"), 'Calendar management should be routed through the admin panel.');
$assert(str_contains($routes, "middleware(['auth', 'admin'])"), 'Calendar management routes should require an administrator.');
$calendarView = (string)file_get_contents($pluginRoot . '/views/calendar.php');
$assert(str_contains($calendarView, "'content_class' => 'fb-content--edge-workspace'"), 'Admin calendar should use the full-width workspace shell.');
$calendarJs = (string)file_get_contents($pluginRoot . '/assets/calendar.js');
$assert(str_contains($calendarJs, 'function eventModal()'), 'Calendar modal should resolve Bootstrap lazily after theme scripts load.');
$assert(!str_contains($calendarJs, 'const modal = window.bootstrap'), 'Calendar must not cache Bootstrap modal before Bootstrap is available.');
$assert(str_contains($calendarJs, 'const canManage = Boolean(config.canManage);'), 'Public calendar compatibility view should remain read-only.');
$calendarCss = (string)file_get_contents($pluginRoot . '/assets/calendar.css');
$assert(str_contains($calendarCss, '.fb-calendar-modal > form'), 'Calendar modal form should have its own scrollable flex layout.');
$assert(str_contains($calendarCss, 'background: transparent;'), 'Calendar switch should not render the oversized background pill.');

$english = require $pluginRoot . '/lang/en.php';
foreach (['ru', 'de', 'zh-cn'] as $locale) {
    $translations = require $pluginRoot . '/lang/' . $locale . '.php';
    $missing = array_diff_key($english, $translations);
    $assert($missing === [], "Calendar {$locale} translations should contain every English key.");
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Calendar unit checks passed.\n");
