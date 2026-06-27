<?php

use FBL\Plugins\PluginInterface;

final class FireballPluginToyCarRental implements PluginInterface
{
    public const SLUG = 'toy-car-rental';

    public function install(): void
    {
        foreach (self::defaultSettings() as $key => $value) {
            if (plugin_setting(self::SLUG, $key, null) === null) {
                plugin_setting_set(self::SLUG, $key, $value);
            }
        }
    }

    public function uninstall(): void
    {
        // Uninstall is intentionally not exposed in the base plugin UI yet.
    }

    public function activate(): void
    {
        fireball_event('toy_rental.activated', ['slug' => self::SLUG]);
    }

    public function deactivate(): void
    {
        fireball_event('toy_rental.deactivated', ['slug' => self::SLUG]);
    }

    public function boot(): void
    {
        add_filter('admin_menu', function (array $menu): array {
            $menu[] = [
                'label' => 'Прокат машинок',
                'href' => base_href('/admin/toy-rental'),
                'icon' => 'activity',
            ];

            return $menu;
        });

        add_filter('notification_feed_items', [self::class, 'notificationFeedItems'], 10);
    }

    public static function defaultSettings(): array
    {
        return [
            'default_duration' => 10,
            'default_price' => 300,
            'currency' => '₽',
            'sound_enabled' => true,
            'toast_enabled' => true,
            'auto_refresh_seconds' => 0,
        ];
    }

    public static function settings(): array
    {
        $settings = [];
        foreach (self::defaultSettings() as $key => $default) {
            $settings[$key] = plugin_setting(self::SLUG, $key, $default);
        }

        $settings['default_duration'] = max(1, (int)$settings['default_duration']);
        $settings['default_price'] = self::money($settings['default_price']);
        $settings['currency'] = trim((string)$settings['currency']) ?: '₽';
        $settings['sound_enabled'] = (bool)$settings['sound_enabled'];
        $settings['toast_enabled'] = (bool)$settings['toast_enabled'];
        $settings['auto_refresh_seconds'] = max(0, (int)$settings['auto_refresh_seconds']);

        return $settings;
    }

    public static function saveSettings(array $data): void
    {
        $settings = [
            'default_duration' => max(1, min(1440, (int)($data['default_duration'] ?? 10))),
            'default_price' => self::money($data['default_price'] ?? 0),
            'currency' => mb_substr(trim((string)($data['currency'] ?? '₽')), 0, 12),
            'sound_enabled' => !empty($data['sound_enabled']),
            'toast_enabled' => !empty($data['toast_enabled']),
            'auto_refresh_seconds' => max(0, min(3600, (int)($data['auto_refresh_seconds'] ?? 0))),
        ];

        if ($settings['currency'] === '') {
            $settings['currency'] = '₽';
        }

        foreach ($settings as $key => $value) {
            plugin_setting_set(self::SLUG, $key, $value);
        }
    }

    public static function tabs(string $active): array
    {
        $items = [
            'dashboard' => ['Панель оператора', '/admin/toy-rental', 'ci-layout'],
            'cars' => ['Машинки', '/admin/toy-rental/cars', 'ci-settings'],
            'active' => ['Активные поездки', '/admin/toy-rental/active', 'ci-activity'],
            'history' => ['История', '/admin/toy-rental/rides', 'ci-calendar'],
            'stats' => ['Статистика', '/admin/toy-rental/stats', 'ci-bar-chart'],
            'settings' => ['Настройки', '/admin/toy-rental/settings', 'ci-settings'],
        ];

        foreach ($items as $key => $item) {
            $items[$key] = [
                'key' => $key,
                'label' => $item[0],
                'href' => base_href($item[1]),
                'icon' => $item[2],
                'active' => $key === $active,
            ];
        }

        return array_values($items);
    }

    public static function viewData(string $active, array $data = []): array
    {
        $assetBase = base_href('/admin/toy-rental/assets/');

        return array_merge([
            'tabs' => self::tabs($active),
            'settings' => self::settings(),
            'styles' => [$assetBase . 'toy-rental.css'],
            'footer_scripts' => [$assetBase . 'toy-rental.js'],
        ], $data);
    }

    public static function cars(bool $includeHidden = false): array
    {
        $where = $includeHidden ? '' : "WHERE status <> 'hidden'";

        return db()->query(
            "SELECT * FROM toy_rental_cars {$where} ORDER BY sort_order ASC, id ASC"
        )->get() ?: [];
    }

    public static function carsForOperator(): array
    {
        self::markOverdueRides();
        $cars = self::cars();
        $rides = self::activeRidesByCar();

        foreach ($cars as &$car) {
            $car['active_ride'] = $rides[(int)$car['id']] ?? null;
        }
        unset($car);

        return $cars;
    }

    public static function car(int $id): ?array
    {
        $row = db()->query('SELECT * FROM toy_rental_cars WHERE id = ? LIMIT 1', [$id])->getOne();

        return is_array($row) ? $row : null;
    }

    public static function saveCar(array $data, ?int $id = null): int
    {
        $now = date('Y-m-d H:i:s');
        $normalized = [
            'name' => trim((string)($data['name'] ?? '')),
            'number' => trim((string)($data['number'] ?? '')),
            'color' => trim((string)($data['color'] ?? '')),
            'status' => self::carStatus((string)($data['status'] ?? 'available')),
            'price_per_minute' => self::money($data['price_per_minute'] ?? 0),
            'price_per_ride' => self::money($data['price_per_ride'] ?? 0),
            'image' => trim((string)($data['image'] ?? '')),
            'sort_order' => max(0, (int)($data['sort_order'] ?? 0)),
        ];

        if ($normalized['name'] === '' || $normalized['number'] === '') {
            throw new RuntimeException('Укажите название и номер машинки.');
        }

        if ($id !== null) {
            db()->query(
                'UPDATE toy_rental_cars
                 SET name = ?, number = ?, color = ?, status = ?, price_per_minute = ?, price_per_ride = ?, image = ?, sort_order = ?, updated_at = ?
                 WHERE id = ?',
                [
                    $normalized['name'],
                    $normalized['number'],
                    $normalized['color'],
                    $normalized['status'],
                    $normalized['price_per_minute'],
                    $normalized['price_per_ride'],
                    $normalized['image'],
                    $normalized['sort_order'],
                    $now,
                    $id,
                ]
            );

            return $id;
        }

        db()->query(
            'INSERT INTO toy_rental_cars (name, number, color, status, price_per_minute, price_per_ride, image, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $normalized['name'],
                $normalized['number'],
                $normalized['color'],
                $normalized['status'],
                $normalized['price_per_minute'],
                $normalized['price_per_ride'],
                $normalized['image'],
                $normalized['sort_order'],
                $now,
                $now,
            ]
        );

        return (int)db()->getInsertId();
    }

    public static function hideCar(int $id): void
    {
        $car = self::car($id);
        if (!$car) {
            throw new RuntimeException('Машинка не найдена.');
        }
        if ((string)$car['status'] === 'rented') {
            throw new RuntimeException('Нельзя скрыть машинку в прокате.');
        }

        db()->query(
            "UPDATE toy_rental_cars SET status = 'hidden', updated_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public static function activeRides(): array
    {
        self::markOverdueRides();

        return db()->query(
            "SELECT r.*, c.name AS car_name, c.number AS car_number, c.color AS car_color
             FROM toy_rental_rides r
             INNER JOIN toy_rental_cars c ON c.id = r.car_id
             WHERE r.status IN ('active', 'overdue')
             ORDER BY r.planned_end_at ASC"
        )->get() ?: [];
    }

    public static function activeRidesByCar(): array
    {
        $items = [];
        foreach (self::activeRides() as $ride) {
            $items[(int)$ride['car_id']] = $ride;
        }

        return $items;
    }

    public static function startRide(array $data): void
    {
        $carId = (int)($data['car_id'] ?? 0);
        $car = self::car($carId);
        if (!$car || (string)$car['status'] !== 'available') {
            throw new RuntimeException('Машинка недоступна для проката.');
        }

        $duration = max(1, min(1440, (int)($data['duration_minutes'] ?? self::settings()['default_duration'])));
        $now = time();
        $startedAt = date('Y-m-d H:i:s', $now);
        $plannedEndAt = date('Y-m-d H:i:s', $now + $duration * 60);
        $amount = self::money($data['payment_amount'] ?? $car['price_per_ride'] ?? 0);

        db()->beginTransaction();
        try {
            db()->query(
                "INSERT INTO toy_rental_rides
                    (car_id, customer_name, customer_phone, started_at, planned_end_at, payment_amount, payment_method, payment_status, status, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)",
                [
                    $carId,
                    trim((string)($data['customer_name'] ?? '')),
                    trim((string)($data['customer_phone'] ?? '')),
                    $startedAt,
                    $plannedEndAt,
                    $amount,
                    self::paymentMethod((string)($data['payment_method'] ?? 'cash')),
                    self::paymentStatus((string)($data['payment_status'] ?? 'paid')),
                    trim((string)($data['notes'] ?? '')),
                    $startedAt,
                    $startedAt,
                ]
            );
            db()->query(
                "UPDATE toy_rental_cars SET status = 'rented', updated_at = ? WHERE id = ?",
                [$startedAt, $carId]
            );
            db()->commit();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }
    }

    public static function completeRide(int $rideId, array $data = []): void
    {
        $ride = db()->query(
            "SELECT * FROM toy_rental_rides WHERE id = ? AND status IN ('active', 'overdue') LIMIT 1",
            [$rideId]
        )->getOne();
        if (!$ride) {
            throw new RuntimeException('Активная поездка не найдена.');
        }

        $endedAt = time();
        $startedAt = strtotime((string)$ride['started_at']) ?: $endedAt;
        $duration = max(1, (int)ceil(($endedAt - $startedAt) / 60));
        $amount = array_key_exists('payment_amount', $data)
            ? self::money($data['payment_amount'])
            : self::money($ride['payment_amount']);
        $method = self::paymentMethod((string)($data['payment_method'] ?? $ride['payment_method']));
        $paymentStatus = self::paymentStatus((string)($data['payment_status'] ?? $ride['payment_status']));

        db()->beginTransaction();
        try {
            db()->query(
                "UPDATE toy_rental_rides
                 SET ended_at = ?, duration_minutes = ?, payment_amount = ?, payment_method = ?, payment_status = ?, status = 'completed', updated_at = ?
                 WHERE id = ?",
                [date('Y-m-d H:i:s', $endedAt), $duration, $amount, $method, $paymentStatus, date('Y-m-d H:i:s', $endedAt), $rideId]
            );
            db()->query(
                "UPDATE toy_rental_cars SET status = 'available', updated_at = ? WHERE id = ?",
                [date('Y-m-d H:i:s', $endedAt), (int)$ride['car_id']]
            );
            db()->commit();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $exception;
        }
    }

    public static function history(array $filters = []): array
    {
        $where = [];
        $params = [];
        $dateFilter = (string)($filters['date_filter'] ?? 'today');

        if ($dateFilter === 'today') {
            $where[] = 'r.started_at >= ? AND r.started_at < ?';
            $params[] = date('Y-m-d 00:00:00');
            $params[] = date('Y-m-d 00:00:00', strtotime('+1 day'));
        } elseif ($dateFilter === 'yesterday') {
            $where[] = 'r.started_at >= ? AND r.started_at < ?';
            $params[] = date('Y-m-d 00:00:00', strtotime('-1 day'));
            $params[] = date('Y-m-d 00:00:00');
        } elseif ($dateFilter === 'period') {
            $from = trim((string)($filters['date_from'] ?? ''));
            $to = trim((string)($filters['date_to'] ?? ''));
            if ($from !== '') {
                $where[] = 'r.started_at >= ?';
                $params[] = $from . ' 00:00:00';
            }
            if ($to !== '') {
                $where[] = 'r.started_at <= ?';
                $params[] = $to . ' 23:59:59';
            }
        }

        foreach (['payment_method', 'payment_status'] as $field) {
            if (!empty($filters[$field])) {
                $where[] = 'r.' . $field . ' = ?';
                $params[] = (string)$filters[$field];
            }
        }

        if (!empty($filters['car_id'])) {
            $where[] = 'r.car_id = ?';
            $params[] = (int)$filters['car_id'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return db()->query(
            "SELECT r.*, c.name AS car_name, c.number AS car_number
             FROM toy_rental_rides r
             INNER JOIN toy_rental_cars c ON c.id = r.car_id
             {$whereSql}
             ORDER BY r.started_at DESC
             LIMIT 300",
            $params
        )->get() ?: [];
    }

    public static function todayStats(): array
    {
        self::markOverdueRides();
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 00:00:00', strtotime('+1 day'));
        $rows = db()->query(
            "SELECT r.*, c.name AS car_name, c.number AS car_number
             FROM toy_rental_rides r
             INNER JOIN toy_rental_cars c ON c.id = r.car_id
             WHERE r.started_at >= ? AND r.started_at < ?",
            [$start, $end]
        )->get() ?: [];

        $stats = [
            'rides_total' => count($rows),
            'active' => 0,
            'completed' => 0,
            'overdue' => 0,
            'revenue_total' => 0.0,
            'revenue_cash' => 0.0,
            'revenue_card' => 0.0,
            'avg_duration' => 0,
            'popular_car' => '—',
        ];
        $durations = [];
        $cars = [];

        foreach ($rows as $row) {
            $status = (string)$row['status'];
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            if ((string)$row['payment_status'] === 'paid') {
                $amount = self::money($row['payment_amount']);
                $stats['revenue_total'] += $amount;
                if ((string)$row['payment_method'] === 'cash') {
                    $stats['revenue_cash'] += $amount;
                }
                if ((string)$row['payment_method'] === 'card') {
                    $stats['revenue_card'] += $amount;
                }
            }
            if (!empty($row['duration_minutes'])) {
                $durations[] = (int)$row['duration_minutes'];
            }
            $carKey = (string)$row['car_id'];
            $cars[$carKey]['count'] = ($cars[$carKey]['count'] ?? 0) + 1;
            $cars[$carKey]['label'] = trim((string)$row['car_name'] . ' #' . (string)$row['car_number']);
        }

        if ($durations) {
            $stats['avg_duration'] = (int)round(array_sum($durations) / count($durations));
        }
        if ($cars) {
            uasort($cars, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
            $stats['popular_car'] = (string)reset($cars)['label'];
        }

        return $stats;
    }

    public static function markOverdueRides(): void
    {
        db()->query(
            "UPDATE toy_rental_rides
             SET status = 'overdue', updated_at = ?
             WHERE status = 'active' AND planned_end_at < ?",
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
    }

    public static function notificationFeedItems(array $items, int $userId, bool $isAdmin, int $limit): array
    {
        if (!$isAdmin) {
            return $items;
        }

        try {
            self::markOverdueRides();
            $limit = max(1, min(10, $limit));
            $rows = db()->query(
                "SELECT r.id, r.planned_end_at, c.name AS car_name, c.number AS car_number
                 FROM toy_rental_rides r
                 INNER JOIN toy_rental_cars c ON c.id = r.car_id
                 WHERE r.status = 'overdue'
                 ORDER BY r.planned_end_at ASC, r.id ASC
                 LIMIT {$limit}"
            )->get() ?: [];
        } catch (Throwable $exception) {
            log_error_details('Toy rental notification feed failed', [
                'user_id' => $userId,
            ], $exception);

            return $items;
        }

        foreach ($rows as $row) {
            $plannedAt = strtotime((string)($row['planned_end_at'] ?? '')) ?: time();
            $minutesOverdue = max(1, (int)floor((time() - $plannedAt) / 60));
            $carLabel = trim((string)($row['car_name'] ?? '') . ' №' . (string)($row['car_number'] ?? ''));

            $items[] = [
                'type' => 'toy_rental',
                'source_label' => 'Прокат',
                'title' => 'Время поездки закончилось',
                'text' => trim($carLabel . ': просрочка ' . $minutesOverdue . ' мин.'),
                'url' => base_href('/admin/toy-rental'),
                'created_at' => (string)($row['planned_end_at'] ?? date('Y-m-d H:i:s')),
                'time' => date('H:i', $plannedAt),
                'sort_id' => (int)($row['id'] ?? 0),
            ];
        }

        return $items;
    }

    public static function statusLabel(string $status): string
    {
        return [
            'available' => 'Свободна',
            'rented' => 'В прокате',
            'maintenance' => 'Обслуживание',
            'hidden' => 'Скрыта',
            'active' => 'Активна',
            'completed' => 'Завершена',
            'cancelled' => 'Отменена',
            'overdue' => 'Просрочена',
        ][$status] ?? $status;
    }

    public static function money(mixed $value): float
    {
        return max(0, round((float)str_replace(',', '.', (string)$value), 2));
    }

    private static function carStatus(string $status): string
    {
        return in_array($status, ['available', 'rented', 'maintenance', 'hidden'], true) ? $status : 'available';
    }

    private static function paymentMethod(string $method): string
    {
        return in_array($method, ['cash', 'card', 'transfer', 'other'], true) ? $method : 'cash';
    }

    private static function paymentStatus(string $status): string
    {
        return in_array($status, ['unpaid', 'paid', 'refunded'], true) ? $status : 'paid';
    }
}
