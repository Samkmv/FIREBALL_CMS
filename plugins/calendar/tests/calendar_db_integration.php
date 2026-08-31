<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('calendar', dirname(__DIR__) . '/lang');

use Fireball\Calendar\Repositories\EventRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$actor = db()->query("SELECT id, role FROM users WHERE role IN ('creator', 'admin') ORDER BY id ASC LIMIT 1")->getOne()
    ?: db()->query('SELECT id, role FROM users ORDER BY id ASC LIMIT 1')->getOne();
$assert(is_array($actor), 'At least one CMS user is required.');

$recipient = db()->query(
    "SELECT id, role FROM users WHERE id <> ?
     ORDER BY CASE WHEN role IN ('creator', 'admin') THEN 1 ELSE 0 END, id ASC LIMIT 1",
    [(int)$actor['id']]
)->getOne();
$isAdmin = in_array((string)$actor['role'], ['creator', 'admin'], true);
$isShared = $isAdmin && is_array($recipient);
$repository = new EventRepository();
$eventIds = [];
$start = (new DateTimeImmutable('+30 days'))->setTime(10, 0);
$end = $start->modify('+1 hour');
$until = $start->modify('+1 day')->setTime(23, 59, 59);

try {
    $eventId = $repository->create([
        'title' => 'Calendar integration ' . bin2hex(random_bytes(4)),
        'description' => 'Temporary integration fixture',
        'starts_at' => $start->format('Y-m-d H:i:s'),
        'ends_at' => $end->format('Y-m-d H:i:s'),
        'recurrence' => 'daily',
        'recurrence_until' => $until->format('Y-m-d'),
        'visibility' => $isShared ? 'users' : 'personal',
        'audience_user_ids' => $isShared ? [(int)$recipient['id']] : [],
        'reminders' => [
            ['value' => 10, 'unit' => 'days', 'time' => '09:15', 'site' => true, 'push' => false],
            ['value' => 1, 'unit' => 'hours', 'site' => false, 'push' => true],
        ],
    ], $actor, $isAdmin);
    $eventIds[] = $eventId;

    $rangeStart = $start->modify('-1 day')->setTime(0, 0);
    $rangeEnd = $start->modify('+3 days')->setTime(0, 0);
    $items = $repository->visibleBetween($actor, $rangeStart, $rangeEnd);
    $ownItems = array_values(array_filter($items, static fn(array $item): bool => (int)$item['event_id'] === $eventId));
    $assert(count($ownItems) === 2, 'Daily series should return both expected occurrences.');
    $assert(count($ownItems[0]['reminders'] ?? []) === 2, 'Both reminders should round-trip through the database.');
    $assert((int)$ownItems[0]['reminders'][0]['push_notification'] === 0, 'Site-only reminder should remain site-only.');

    if ($isShared) {
        $sharedItems = $repository->visibleBetween($recipient, $rangeStart, $rangeEnd);
        $sharedItems = array_values(array_filter($sharedItems, static fn(array $item): bool => (int)$item['event_id'] === $eventId));
        $assert(count($sharedItems) === 2, 'Selected recipient should see the shared series.');
        $recipientCanAdminister = in_array((string)$recipient['role'], ['creator', 'admin'], true);
        $assert(
            $sharedItems[0]['editable'] === $recipientCanAdminister,
            'Shared series editability should follow the recipient role.'
        );
    }

    $copyId = $repository->duplicate($eventId, $actor, $isAdmin);
    $eventIds[] = $copyId;
    $copy = $repository->findManageable($copyId, (int)$actor['id'], $isAdmin);
    $assert(is_array($copy) && str_contains((string)$copy['title'], FireballPluginCalendar::t('calendar_copy_suffix')), 'Duplicated event should be manageable and marked as a copy.');

    fwrite(STDOUT, "Calendar database integration checks passed.\n");
} finally {
    if ($eventIds !== []) {
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        db()->query("DELETE FROM calendar_reminder_deliveries WHERE event_id IN ({$placeholders})", $eventIds);
        db()->query("DELETE FROM calendar_reminders WHERE event_id IN ({$placeholders})", $eventIds);
        db()->query("DELETE FROM calendar_event_users WHERE event_id IN ({$placeholders})", $eventIds);
        db()->query("DELETE FROM calendar_events WHERE id IN ({$placeholders})", $eventIds);
    }
}
