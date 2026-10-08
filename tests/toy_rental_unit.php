<?php
declare(strict_types=1);
require __DIR__ . '/fixtures/toy_rental_bootstrap.php';
$GLOBALS['toyLocale'] = 'ru';
$checks = 0;
function check(bool $value, string $label): void { $GLOBALS['checks']++; if (!$value) throw new RuntimeException($label); }
function fails(callable $call, string $label): void { try { $call(); } catch (RuntimeException) { check(true, $label); return; } check(false, $label); }
final class ToyDb {
    public array $cars = [], $rides = [];
    private array $rows = [];
    private bool $transaction = false;
    private int $affected = 0, $insertId = 0;
    public function beginTransaction(): void { $this->transaction = true; }
    public function commit(): void { $this->transaction = false; }
    public function rollBack(): void { $this->transaction = false; }
    public function inTransaction(): bool { return $this->transaction; }
    public function rowCount(): int { return $this->affected; }
    public function getInsertId(): int { return $this->insertId; }
    public function get(): array { return $this->rows; }
    public function getOne(): ?array { return $this->rows[0] ?? null; }
    public function getColumn(): int { return 1; }
    public function query(string $sql, array $args = []): self {
        $sql = preg_replace('/\s+/', ' ', trim($sql)); $this->rows = []; $this->affected = 0;
        if (str_contains($sql, 'information_schema.TABLES')) return $this;
        if (str_starts_with($sql, 'SELECT * FROM toy_rental_cars WHERE id') || str_starts_with($sql, 'SELECT id FROM toy_rental_cars WHERE id')) {
            if (isset($this->cars[$args[0]])) $this->rows[] = $this->cars[$args[0]];
        } elseif (str_starts_with($sql, 'SELECT id FROM toy_rental_rides WHERE car_id')) {
            $this->rows = array_values(array_filter($this->rides, fn($r) => $r['car_id'] === $args[0] && in_array($r['status'], ['active', 'overdue'], true)));
        } elseif (str_starts_with($sql, 'SELECT * FROM toy_rental_rides WHERE id')) {
            if (isset($this->rides[$args[0]]) && in_array($this->rides[$args[0]]['status'], ['active', 'overdue'], true)) $this->rows[] = $this->rides[$args[0]];
        } elseif (str_starts_with($sql, 'SELECT r.*, c.name')) {
            $this->rows = array_values(array_filter($this->rides, fn($r) => in_array($r['status'], ['active', 'overdue'], true) && $r['started_at'] <= $args[0]));
        } elseif (str_starts_with($sql, 'SELECT r.id, r.car_id')) {
            $this->rows = array_values(array_filter($this->rides, fn($r) => $r['status'] === 'active' && $r['billing_type'] === 'fixed' && $r['planned_end_at'] <= $args[0]));
        } elseif (str_starts_with($sql, 'SELECT id FROM toy_rental_rides WHERE status')) {
            $this->rows = array_values(array_filter($this->rides, fn($r) => in_array($r['status'], ['active', 'overdue'], true)));
        } elseif (str_starts_with($sql, 'INSERT INTO toy_rental_rides')) {
            $keys = ['car_id','billing_type','price_per_minute','estimated_minutes','customer_name','customer_phone','started_at','planned_end_at','payment_amount','final_amount','payment_method','payment_status','notes','created_at','updated_at'];
            $ride = array_combine($keys, $args); $ride['status'] = 'active'; $ride['id'] = ++$this->insertId;
            $ride['car_name'] = $this->cars[$ride['car_id']]['name']; $ride['car_number'] = $this->cars[$ride['car_id']]['number'];
            $this->rides[$ride['id']] = $ride;
        } elseif (str_starts_with($sql, 'UPDATE toy_rental_rides SET ended_at')) {
            $keys = ['ended_at','duration_minutes','payment_amount','final_amount','payment_method','payment_status','updated_at'];
            // The UPDATE carries ended_at, duration, amount, calculated, method, status, updated_at, id.
            $id = $args[7]; $this->rides[$id] = array_replace($this->rides[$id], array_combine($keys, array_slice($args, 0, 7)), ['status' => 'completed']); $this->affected = 1;
        } elseif (str_starts_with($sql, "UPDATE toy_rental_rides SET status = 'overdue'")) {
            if (($this->rides[$args[1]]['status'] ?? '') === 'active') { $this->rides[$args[1]]['status'] = 'overdue'; $this->affected = 1; }
        } elseif (str_starts_with($sql, 'UPDATE toy_rental_cars SET name')) {
            $keys = ['name','number','color','status','price_per_minute','image','sort_order','updated_at'];
            $this->cars[$args[8]] = array_replace($this->cars[$args[8]], array_combine($keys, array_slice($args, 0, 8)));
        } elseif (str_starts_with($sql, 'INSERT INTO toy_rental_cars')) {
            $keys = ['name','number','color','status','price_per_minute','image','sort_order','created_at','updated_at'];
            $this->cars[++$this->insertId] = array_combine($keys, $args) + ['id'=>$this->insertId,'price_per_ride'=>0];
        } elseif (str_starts_with($sql, 'UPDATE toy_rental_cars')) {
            $this->cars[$args[1]]['status'] = str_contains($sql, "'rented'") ? 'rented' : 'available'; $this->affected = 1;
        } else { throw new RuntimeException('Unhandled fixture query: ' . $sql); }
        return $this;
    }
}
function db(): ToyDb { return $GLOBALS['toyDb']; }
$database = $GLOBALS['toyDb'] = new ToyDb();
$database->cars[1] = ['id'=>1,'name'=>'Ferrari','number'=>'1','status'=>'available','price_per_minute'=>25,'price_per_ride'=>250];
check(FireballPluginToyCarRental::fixedDurations('30, 5; 10 10, 15') === [5,10,15,30], 'Normalize duration settings');
foreach (['', '0,10', '-5', '1.5', '1441', 'bad'] as $value) fails(fn() => FireballPluginToyCarRental::fixedDurations($value, true), 'Reject invalid durations');
$GLOBALS['toySettings']['default_duration'] = 20;
check(in_array(20, FireballPluginToyCarRental::settings()['fixed_durations'], true), 'Preserve legacy custom duration');
$GLOBALS['toySettings'] = [];
fails(fn() => FireballPluginToyCarRental::saveSettings(['fixed_durations'=>'5,15','default_duration'=>10]), 'Default must be listed');
fails(fn() => FireballPluginToyCarRental::saveSettings(['fixed_durations'=>'5,10,30','default_duration'=>10,'max_ride_minutes'=>15]), 'Limit must cover fixed durations');
check($GLOBALS['toySettings'] === [], 'Invalid settings do not partially save');
FireballPluginToyCarRental::saveSettings(['fixed_durations'=>'5,10,15,30','default_duration'=>10,'max_ride_minutes'=>120,'overdue_push_enabled'=>1]);
FireballPluginToyCarRental::startRide(['car_id'=>1,'billing_type'=>'fixed','duration_minutes'=>5,'payment_amount'=>9999]);
$fixed = $database->rides[1];
check($fixed['payment_amount'] === 125.0 && $fixed['final_amount'] === 125.0 && $fixed['price_per_minute'] === 25.0, 'Fixed amount is minute rate times duration; ignore a separate posted amount');
check(strtotime($fixed['planned_end_at']) - strtotime($fixed['started_at']) === 300, 'Selected duration reaches server');
fails(fn() => FireballPluginToyCarRental::startRide(['car_id'=>1,'billing_type'=>'metered']), 'Cannot start a rented car');
$database->rides[1]['planned_end_at'] = date('Y-m-d H:i:s');
check(FireballPluginToyCarRental::markOverdueRides() === 1, 'Expiry includes the exact boundary');
check(FireballPluginToyCarRental::markOverdueRides() === 0, 'Repeated sync does not resend an alert');
check(count(App\Services\NotificationService::$messages) === 1, 'One notification per expiry');
$message = App\Services\NotificationService::$messages[0];
check($message['store_unread'] && $message['push_notification'] && $message['metadata']['ride_id'] === 1, 'Persist unread alert and use native phone push');
FireballPluginToyCarRental::completeRide(1);
check($database->cars[1]['status'] === 'available' && $database->rides[1]['status'] === 'completed', 'Completion releases the car');
FireballPluginToyCarRental::startRide(['car_id'=>1,'billing_type'=>'metered','payment_status'=>'paid']);
check($database->rides[2]['payment_status'] === 'unpaid' && $database->rides[2]['payment_amount'] === 0.0, 'Metered rides charge at completion');
$database->rides[2]['started_at'] = date('Y-m-d H:i:s', time() - 86400);
$start = strtotime($database->rides[2]['started_at']);
check(FireballPluginToyCarRental::expireLongRides() === 1, 'Forgotten ride closes');
check($database->rides[2]['duration_minutes'] === 120 && strtotime($database->rides[2]['ended_at']) === $start + 7200, 'Use deadline rather than delayed scheduler time');
check($database->rides[2]['payment_amount'] === 3000.0 && $database->rides[2]['payment_status'] === 'unpaid', 'Do not bill a full day or invent payment');
check(FireballPluginToyCarRental::expireLongRides() === 0 && count(App\Services\NotificationService::$messages) === 2, 'Limit notification only once');
$GLOBALS['toySettings']['overdue_push_enabled'] = false;
FireballPluginToyCarRental::startRide(['car_id'=>1,'billing_type'=>'fixed']);
$database->rides[3]['planned_end_at'] = date('Y-m-d H:i:s', time()-1);
FireballPluginToyCarRental::markOverdueRides();
check(App\Services\NotificationService::$messages[2]['store_unread'] && !App\Services\NotificationService::$messages[2]['push_notification'], 'Disabling push keeps the center alert');
(new FireballPluginToyCarRental())->deactivate();
check($database->rides[3]['status'] === 'completed' && $database->cars[1]['status'] === 'available', 'Deactivation stops open rides');
FireballPluginToyCarRental::startRide(['car_id'=>1,'billing_type'=>'metered']);
(new FireballPluginToyCarRental())->uninstall();
check($database->rides[4]['status'] === 'completed', 'Uninstall stops open rides');
$GLOBALS['toySettings']['default_price'] = 9999;
check(!array_key_exists('default_price', FireballPluginToyCarRental::settings()), 'Legacy fixed price is not exposed or used');
foreach ([10=>250.0,15=>375.0,30=>750.0] as $minutes=>$amount) {
    FireballPluginToyCarRental::startRide(['car_id'=>1,'billing_type'=>'fixed','duration_minutes'=>$minutes]);
    $id = $database->getInsertId();
    check($database->rides[$id]['payment_amount'] === $amount, 'Minute-based fixed price for ' . $minutes . ' minutes');
    $database->cars[1]['price_per_minute'] = 99;
    FireballPluginToyCarRental::completeRide($id);
    check($database->rides[$id]['payment_amount'] === $amount && $database->rides[$id]['price_per_minute'] === 25.0, 'Completion keeps the start-time price snapshot');
    $database->cars[1]['price_per_minute'] = 25;
}
$database->cars[1]['price_per_minute'] = 0;
$GLOBALS['toySettings']['default_minute_price'] = 12.5;
FireballPluginToyCarRental::startRide(['car_id'=>1,'billing_type'=>'fixed','duration_minutes'=>5]);
$id = $database->getInsertId();
check($database->rides[$id]['payment_amount'] === 62.5 && FireballPluginToyCarRental::minutePrice($database->cars[1]) === 12.5, 'Preview and start use the default minute price when car rate is missing');
FireballPluginToyCarRental::completeRide($id);
FireballPluginToyCarRental::saveCar(['name'=>'Ferrari','number'=>'1','price_per_minute'=>'12,55','price_per_ride'=>9999], 1);
check($database->cars[1]['price_per_minute'] === 12.55 && $database->cars[1]['price_per_ride'] === 250, 'Car editing stores the minute price and does not overwrite a legacy separate price');
$newId = FireballPluginToyCarRental::saveCar(['name'=>'BMW','number'=>'2','price_per_minute'=>20,'price_per_ride'=>9999]);
check($database->cars[$newId]['price_per_minute'] === 20.0 && $database->cars[$newId]['price_per_ride'] === 0, 'New cars do not accept a separate fixed price');
(new FireballPluginToyCarRental())->boot();
$jobs = $GLOBALS['toyFilters']['fireball_scheduled_jobs']([]);
check($jobs['toy_rental_expiry']['schedule'] === '* * * * *' && method_exists($jobs['toy_rental_expiry']['class'], 'handle'), 'CMS background scheduler processes rides without the page');
$manifest = json_decode(file_get_contents(dirname(__DIR__).'/plugins/toy-car-rental/plugin.json'), true, flags: JSON_THROW_ON_ERROR);
foreach (['ru','en','de','zh-cn'] as $locale) {
    check(!empty($manifest['name_i18n'][$locale]) && !empty($manifest['description_i18n'][$locale]), 'Localized plugin card ' . $locale);
    $translations = require dirname(__DIR__).'/plugins/toy-car-rental/lang/'.$locale.'.php';
    $ru = require dirname(__DIR__).'/plugins/toy-car-rental/lang/ru.php';
    check(array_keys($translations) === array_keys($ru), 'Complete translation keys ' . $locale);
}
echo 'Toy rental unit checks passed: ' . $checks . PHP_EOL;
