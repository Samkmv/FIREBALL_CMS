<?php
$currency = (string)$settings['currency'];
$t = static fn(string $key): string => htmlSC(FireballPluginToyCarRental::t($key));
$activeOnly = !empty($operatorActiveOnly);
$visibleCars = $activeOnly ? array_values(array_filter($cars, static fn(array $car): bool => !empty($car['active_ride']))) : $cars;
?>
<div class="toy-rental-grid" data-toy-rental-grid data-active-only="<?= $activeOnly ? 'true' : 'false' ?>">
    <?php foreach ($visibleCars as $car): ?>
        <?= plugin_view('toy-car-rental', 'car-card', ['car' => $car, 'settings' => $settings], false) ?>
    <?php endforeach; ?>
</div>
<div class="fb-card p-4 text-center text-body-secondary <?= $visibleCars ? 'd-none' : '' ?>" data-toy-rental-empty>
    <?= $t($activeOnly ? 'toy_rental_active_empty' : 'toy_rental_empty_cars_text') ?>
    <?php if (!$activeOnly): ?>
        <a class="btn btn-primary ms-2" href="<?= base_href('/admin/toy-rental/cars/create') ?>"><?= $t('toy_rental_add_car') ?></a>
    <?php endif; ?>
</div>
<div class="toy-rental-notices" data-toy-rental-notices aria-live="polite" aria-relevant="additions"></div>
<div class="modal fade" id="toyCompleteRide" tabindex="-1" aria-labelledby="toyCompleteRideLabel" aria-hidden="true" data-toy-rental-complete-modal>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content fb-card" action="<?= base_href('/admin/toy-rental/rides/complete') ?>" method="post" data-toy-rental-payment-form>
            <?= get_csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="modal-header">
                <h2 class="modal-title h5" id="toyCompleteRideLabel"><?= $t('toy_rental_complete_metered_title') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $t('toy_rental_close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="fw-medium" data-toy-rental-modal-car></p>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="toyCompleteDuration"><?= $t('toy_rental_actual_time') ?></label>
                        <input class="form-control" id="toyCompleteDuration" readonly data-toy-rental-modal-duration>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="toyCompleteCalculated"><?= $t('toy_rental_calculated_amount') ?></label>
                        <input class="form-control" id="toyCompleteCalculated" readonly data-toy-rental-modal-calculated>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="toyCompleteAmount"><?= $t('toy_rental_final_amount') ?></label>
                        <input class="form-control" id="toyCompleteAmount" type="number" name="payment_amount" min="0" step="0.01" data-toy-rental-final-amount>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="toyCompleteMethod"><?= $t('toy_rental_table_payment_method') ?></label>
                        <select class="form-select" id="toyCompleteMethod" name="payment_method">
                            <?php foreach (['cash', 'card', 'transfer', 'other'] as $method): ?>
                                <option value="<?= $method ?>"><?= htmlSC(FireballPluginToyCarRental::paymentMethodLabel($method)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="toyCompleteStatus"><?= $t('toy_rental_table_payment_status') ?></label>
                        <select class="form-select" id="toyCompleteStatus" name="payment_status">
                            <?php foreach (['paid', 'unpaid'] as $status): ?>
                                <option value="<?= $status ?>"><?= htmlSC(FireballPluginToyCarRental::paymentStatusLabel($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="alert alert-danger mt-3 mb-0 d-none" role="alert" data-toy-rental-modal-error></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal"><?= $t('toy_rental_cancel') ?></button>
                <button class="btn btn-primary" type="submit"><?= $t('toy_rental_complete') ?></button>
            </div>
        </form>
    </div>
</div>
<script>
    window.toyRentalSettings = <?= json_encode([
        'soundEnabled' => (bool)$settings['sound_enabled'],
        'autoRefreshSeconds' => (int)$settings['auto_refresh_seconds'],
        'stateUrl' => base_href('/admin/toy-rental/state'),
        'syncOverdueUrl' => base_href('/admin/toy-rental/rides/sync-overdue'),
        'maxRideMinutes' => (int)$settings['max_ride_minutes'],
        'currency' => $currency,
        'labels' => [
            'overdue' => FireballPluginToyCarRental::t('toy_rental_status_overdue'),
            'minutes' => FireballPluginToyCarRental::t('toy_rental_min_short'),
            'timeUp' => FireballPluginToyCarRental::t('toy_rental_time_up_message'),
            'limitReached' => FireballPluginToyCarRental::t('toy_rental_limit_reached'),
            'error' => FireballPluginToyCarRental::t('toy_rental_error_request'),
            'close' => FireballPluginToyCarRental::t('toy_rental_close'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
