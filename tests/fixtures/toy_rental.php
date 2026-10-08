<?php
declare(strict_types=1);
require __DIR__ . '/toy_rental_bootstrap.php';
$GLOBALS['toyLocale'] = in_array($argv[1] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[1] : 'ru';
$mode = $argv[2] ?? 'dashboard';
$clock = (int)($argv[3] ?? time());
$carId = (int)($argv[4] ?? 1);
$duration = (int)($argv[5] ?? 10);
$settings = FireballPluginToyCarRental::settings();
$car = ['id' => $carId, 'name' => $carId === 1 ? 'Ferrari' : ($carId === 2 ? 'BMW' : 'Mercedes'), 'number' => (string)$carId, 'color' => 'red', 'status' => 'available', 'price_per_minute' => 25, 'price_per_ride' => 250, 'active_ride' => null];
$makeRide = static function (string $type, int $started, int $end, int $id) use ($carId, $duration): array {
    return ['id' => $id, 'car_id' => $carId, 'billing_type' => $type, 'status' => 'active', 'started_at' => date('Y-m-d H:i:s', $started), 'planned_end_at' => date('Y-m-d H:i:s', $end), 'price_per_minute' => 25, 'payment_amount' => $type === 'fixed' ? $duration * 25 : 0, 'payment_status' => $type === 'fixed' ? 'paid' : 'unpaid', 'estimated_minutes' => $type === 'fixed' ? $duration : null];
};
if (in_array($mode, ['fixed', 'metered', 'overdue', 'cap'], true)) {
    $car['status'] = 'rented';
    $car['active_ride'] = $makeRide(in_array($mode, ['metered', 'cap'], true) ? 'metered' : 'fixed', $mode === 'cap' ? $clock - 7200 : $clock, $mode === 'overdue' ? $clock - 1 : $clock + $duration * 60, 100 + $carId);
    if ($mode === 'overdue') $car['active_ride']['status'] = 'overdue';
    echo plugin_view('toy-car-rental', 'car-card', compact('car', 'settings'), false); exit;
}
if (in_array($mode, ['available', 'maintenance'], true)) {
    if ($mode === 'maintenance') $car['status'] = 'maintenance';
    echo plugin_view('toy-car-rental', 'car-card', compact('car', 'settings'), false); exit;
}
$cars = [];
for ($id = 1; $id <= 6; $id++) {
    $item = $car; $item['id'] = $id; $item['number'] = (string)$id;
    $item['name'] = ['Ferrari', 'BMW', 'Mercedes', 'Audi', 'Mini', 'Jeep'][$id - 1];
    if ($id === 5) { $item['active_ride'] = $makeRide('metered', $clock - 30, $clock + 600, 105); $item['status'] = 'rented'; }
    if ($id === 6) $item['status'] = 'maintenance';
    $cars[] = $item;
}
$tabs = FireballPluginToyCarRental::tabs($mode === 'active' ? 'active' : 'dashboard');
$stats = ['rides_total' => 1, 'active' => 1, 'overdue' => 0, 'revenue_total' => 0];
echo plugin_view('toy-car-rental', match ($mode) { 'active'=>'rides-active', 'settings'=>'settings', 'car-form'=>'car-form', default=>'admin-dashboard' }, compact('cars', 'car', 'tabs', 'stats', 'settings'), false);
