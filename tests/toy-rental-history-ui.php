<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
final class FireballPluginToyCarRental {
    public const PAYMENT_METHODS = ['cash', 'transfer'];
    public static function t(string $key): string { return $key; }
    public static function paymentMethodLabel(string $value): string { return $value; }
    public static function paymentStatusLabel(string $value): string { return $value; }
    public static function billingTypeLabel(string $value): string { return $value; }
    public static function statusLabel(string $value): string { return $value; }
}
function htmlSC(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_href(string $path): string { return '/ru' . $path; }
function get_csrf_field(): string { return '<input type="hidden" name="csrf_token" value="fixture">'; }
function current_url_with_query(): string { return '/ru/admin/toy-rental/rides?date_filter=all&car_id=4'; }
function return_translation(string $key): string { return $key; }
final class HistoryUiView {
    public function renderPartial(string $name, array $data = []): string {
        if (str_starts_with($name, 'admin/shell_')) { return ''; }
        extract($data);
        ob_start();
        require dirname(__DIR__) . '/app/Views/themes/default/' . $name . '.php';
        return ob_get_clean();
    }
}
function view(): HistoryUiView { return new HistoryUiView(); }
$settings = ['currency' => '₽'];
$cars = []; $tabs = [];
$filters = ['date_filter' => 'all', 'date_from' => '', 'date_to' => '', 'car_id' => 0, 'billing_type' => '', 'ride_status' => '', 'payment_method' => '', 'payment_status' => ''];
$rides = [];
foreach (['completed', 'completed', 'cancelled', 'active', 'overdue'] as $i => $status) {
    $rides[] = ['id' => 51 + $i, 'duration_minutes' => 10, 'price_per_minute' => 20, 'final_amount' => 200, 'payment_amount' => 200, 'payment_status' => $i === 1 ? 'unpaid' : 'paid', 'status' => $status, 'billing_type' => 'metered', 'car_name' => 'Car " & #' . (51 + $i), 'car_number' => '1', 'started_at' => '2026-10-12 12:00:00', 'customer_name' => '', 'customer_phone' => '', 'payment_method' => 'cash'];
}
ob_start(); require dirname(__DIR__) . '/plugins/toy-car-rental/views/rides-history.php'; $html = ob_get_clean();
$document = new DOMDocument(); libxml_use_internal_errors(true); $document->loadHTML('<meta charset="UTF-8">' . $html); $xpath = new DOMXPath($document);
$checks = 0;
function check(bool $condition, string $label): void { global $checks; ++$checks; if (!$condition) { throw new RuntimeException($label); } }
foreach (['clear-history' => 1, 'delete' => 6, 'recalculate' => 4, 'pay' => 4] as $action => $count) {
    check($xpath->query('//form[@action="/ru/admin/toy-rental/rides/' . $action . '"]')->length === $count, $action . ' count');
}
check($xpath->query('//form[@action="/ru/admin/toy-rental/rides/clear-history" and @data-admin-delete-form]')->length === 1, 'Clear uses native confirmation');
foreach ($xpath->query('//form[contains(@action,"/rides/delete") or contains(@action,"/rides/recalculate")]') as $form) {
    check($form->hasAttribute('data-admin-delete-form'), 'Native confirmation for ride action');
    check($form->getElementsByTagName('input')->length === 3, 'Ride action contains CSRF, id, return path');
    $id = $xpath->query('input[@name="id"]', $form)->item(0)->getAttribute('value');
    check(!in_array($id, ['54', '55'], true), 'Active/overdue has no destructive controls');
}
check($xpath->query('//form[@data-confirm-variant="primary" and @data-confirm-icon="ci-refresh-cw"]')->length === 4, 'Recalculation uses primary modal');
check($xpath->query('//form[@action="/ru/admin/toy-rental/rides/pay"]/input[@name="payment_method" and @value="cash"]')->length === 2, 'Cash on desktop and mobile');
check($xpath->query('//form[@action="/ru/admin/toy-rental/rides/pay"]/input[@name="payment_method" and @value="transfer"]')->length === 2, 'Transfer on desktop and mobile');
check($xpath->query('//article[contains(@class,"admin-mobile-table-card")]')->length === 5, 'Native CMS mobile cards');
check(str_contains($html, 'data-delete-item="Car &quot; &amp; #51 #51"'), 'Escaped native confirmation item');
echo $checks . " history UI checks passed\n";
