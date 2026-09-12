<?php

namespace Fireball\Calendar\Controllers;

use App\Services\PwaService;
use Fireball\Calendar\Repositories\EventRepository;

final class CalendarController
{
    public function index(): string
    {
        return $this->renderCalendar(true);
    }

    public function viewer(): string
    {
        if (check_admin()) {
            $query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
            response()->redirect(base_href('/admin/calendar') . ($query !== '' ? '?' . $query : ''));
        }

        return $this->renderCalendar(false);
    }

    private function renderCalendar(bool $adminContext): string
    {
        $user = get_user();
        $roles = $adminContext ? (db()->query('SELECT slug, name FROM user_roles ORDER BY id ASC')->get() ?: []) : [];
        $users = $adminContext ? (db()->query('SELECT id, name, login, role FROM users ORDER BY name ASC, id ASC')->get() ?: []) : [];

        return plugin_view('calendar', 'calendar', \FireballPluginCalendar::viewData([
            'title' => \FireballPluginCalendar::t('calendar_title'),
            'user' => $user,
            'is_admin' => $adminContext,
            'admin_context' => $adminContext,
            'can_manage' => $adminContext,
            'events_url' => base_href($adminContext ? '/admin/calendar/events' : '/calendar/events'),
            'roles' => $roles,
            'users' => $users,
            'push_status' => (new PwaService())->pushStatusForUser((int)($user['id'] ?? 0)),
        ]));
    }

    public function events(): void
    {
        try {
            [$start, $end] = $this->range();
            $items = (new EventRepository())->visibleBetween(get_user(), $start, $end);
            response()->json([
                'status' => true,
                'items' => $items,
                'range' => ['start' => $start->format(DATE_ATOM), 'end' => $end->format(DATE_ATOM)],
            ]);
        } catch (\Throwable $exception) {
            $this->error($exception);
        }
    }

    public function create(): void
    {
        try {
            $repository = new EventRepository();
            $id = $repository->create(request()->getData(), get_user(), check_admin());
            response()->json(['status' => true, 'id' => $id, 'message' => \FireballPluginCalendar::t('calendar_saved')]);
        } catch (\Throwable $exception) {
            $this->error($exception);
        }
    }

    public function update(): void
    {
        try {
            $id = (int)get_route_param('id');
            (new EventRepository())->update($id, request()->getData(), get_user(), check_admin());
            response()->json(['status' => true, 'id' => $id, 'message' => \FireballPluginCalendar::t('calendar_saved')]);
        } catch (\Throwable $exception) {
            $this->error($exception);
        }
    }

    public function delete(): void
    {
        try {
            $id = (int)get_route_param('id');
            (new EventRepository())->delete($id, (int)get_user()['id'], check_admin());
            response()->json(['status' => true, 'message' => \FireballPluginCalendar::t('calendar_deleted')]);
        } catch (\Throwable $exception) {
            $this->error($exception);
        }
    }

    public function duplicate(): void
    {
        try {
            $id = (new EventRepository())->duplicate((int)get_route_param('id'), get_user(), check_admin());
            response()->json(['status' => true, 'id' => $id, 'message' => \FireballPluginCalendar::t('calendar_duplicated')]);
        } catch (\Throwable $exception) {
            $this->error($exception);
        }
    }

    public function status(): void
    {
        try {
            $id = (int)get_route_param('id');
            (new EventRepository())->setStatus(
                $id,
                (string)request()->post('status', 'scheduled'),
                (int)get_user()['id'],
                check_admin()
            );
            response()->json(['status' => true, 'message' => \FireballPluginCalendar::t('calendar_saved')]);
        } catch (\Throwable $exception) {
            $this->error($exception);
        }
    }

    private function range(): array
    {
        $timezone = new \DateTimeZone(date_default_timezone_get());
        try {
            $start = new \DateTimeImmutable((string)request()->get('start', 'first day of this month'), $timezone);
            $end = new \DateTimeImmutable((string)request()->get('end', 'first day of next month'), $timezone);
        } catch (\Throwable) {
            throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_dates'));
        }
        if ($end <= $start || $end->getTimestamp() - $start->getTimestamp() > 370 * 86400) {
            throw new \InvalidArgumentException(\FireballPluginCalendar::t('calendar_error_range'));
        }

        return [$start, $end];
    }

    private function error(\Throwable $exception): never
    {
        $code = $exception instanceof \InvalidArgumentException ? 422 : 400;
        log_error_details('Calendar request failed', ['Route' => request()->getPath()], $exception);
        response()->json([
            'status' => false,
            'message' => $exception->getMessage() !== '' ? $exception->getMessage() : \FireballPluginCalendar::t('calendar_error_generic'),
        ], $code);
    }
}
