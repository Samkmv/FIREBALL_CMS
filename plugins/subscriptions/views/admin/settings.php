<?php require __DIR__ . '/shell-open.php'; ?>
    <form class="border rounded-4 p-4 p-lg-5" method="post" action="<?= htmlSC(base_href('/admin/subscriptions/settings/save')) ?>" autocomplete="off" data-subscriptions-settings-form>
        <?= get_csrf_field() ?>
        <div class="alert alert-info"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_settings_urls_hint')) ?></div>
        <?php $gatewayReady = trim((string)$settings['merchant_login']) !== '' && !empty($settings['password1_configured']) && !empty($settings['password2_configured']); ?>
        <div class="alert <?= $gatewayReady ? 'alert-success' : 'alert-danger' ?>" role="status">
            <?= htmlSC(FireballPluginSubscriptions::t($gatewayReady ? 'subscriptions_settings_credentials_ready' : 'subscriptions_settings_credentials_missing')) ?>
            <?php if ($gatewayReady): ?>
                <div class="small mt-2">
                    <?= htmlSC(FireballPluginSubscriptions::t(!empty($settings['test_mode']) ? 'subscriptions_payment_mode_test' : 'subscriptions_payment_mode_live')) ?>
                    · <?= htmlSC(FireballPluginSubscriptions::t('subscriptions_hash_algorithm')) ?>: <?= htmlSC(strtoupper((string)$settings['hash_algorithm'])) ?>
                    · IsTest=<?= !empty($settings['test_mode']) ? '1' : '0' ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_merchant_login')) ?></label><input class="form-control" name="merchant_login" value="<?= htmlSC((string)$settings['merchant_login']) ?>" required></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_hash_algorithm')) ?></label><select class="form-select" name="hash_algorithm"><?php foreach (['md5', 'ripemd160', 'sha1', 'sha256', 'sha384', 'sha512'] as $algorithm): ?><option value="<?= $algorithm ?>" <?= $settings['hash_algorithm'] === $algorithm ? 'selected' : '' ?>><?= strtoupper($algorithm) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_currency')) ?></label><input class="form-control" name="currency" maxlength="3" value="<?= htmlSC((string)$settings['currency']) ?>"></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_password1')) ?></label><input class="form-control" type="password" name="password1" value="" autocomplete="new-password" placeholder="<?= $settings['password1_configured'] ? '••••••••' : '' ?>" <?= $settings['password1_configured'] ? '' : 'required' ?>><div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t($settings['password1_configured'] ? 'subscriptions_secret_hint' : 'subscriptions_secret_required_hint')) ?></div></div>
            <div class="col-md-6"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_password2')) ?></label><input class="form-control" type="password" name="password2" value="" autocomplete="new-password" placeholder="<?= $settings['password2_configured'] ? '••••••••' : '' ?>" <?= $settings['password2_configured'] ? '' : 'required' ?>><div class="form-text"><?= htmlSC(FireballPluginSubscriptions::t($settings['password2_configured'] ? 'subscriptions_secret_hint' : 'subscriptions_secret_required_hint')) ?></div></div>
            <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_timeout')) ?></label><input class="form-control" type="number" name="payment_timeout_minutes" min="5" value="<?= (int)$settings['payment_timeout_minutes'] ?>"></div>
            <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_media_ttl')) ?></label><input class="form-control" type="number" name="media_token_ttl" min="60" max="1800" value="<?= (int)$settings['media_token_ttl'] ?>"></div>
        </div>
        <div class="row g-3 mt-2">
            <?php foreach ([['test_mode', 'subscriptions_test_mode'], ['receipt_enabled', 'subscriptions_receipt_enabled']] as [$key, $label]): ?><div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="<?= $key ?>" value="1" <?= !empty($settings[$key]) ? 'checked' : '' ?>><span class="form-check-label"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></span></label></div><?php endforeach; ?>
        </div>
        <div class="row g-3 mt-2">
            <?php $taxLabels = ['none' => 'subscriptions_receipt_tax_none', 'vat0' => 'subscriptions_receipt_tax_vat0', 'vat5' => 'subscriptions_receipt_tax_vat5', 'vat7' => 'subscriptions_receipt_tax_vat7', 'vat10' => 'subscriptions_receipt_tax_vat10', 'vat20' => 'subscriptions_receipt_tax_vat20']; ?>
            <?php $methodLabels = ['full_payment' => 'subscriptions_receipt_method_full_payment', 'prepayment' => 'subscriptions_receipt_method_prepayment', 'prepayment_full' => 'subscriptions_receipt_method_prepayment_full', 'advance' => 'subscriptions_receipt_method_advance']; ?>
            <?php $objectLabels = ['service' => 'subscriptions_receipt_object_service', 'commodity' => 'subscriptions_receipt_object_commodity', 'payment' => 'subscriptions_receipt_object_payment', 'another' => 'subscriptions_receipt_object_another']; ?>
            <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_receipt_tax')) ?></label><select class="form-select" name="receipt_tax"><?php foreach ($taxLabels as $item => $label): ?><option value="<?= $item ?>" <?= $settings['receipt_tax'] === $item ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_receipt_method')) ?></label><select class="form-select" name="receipt_payment_method"><?php foreach ($methodLabels as $item => $label): ?><option value="<?= $item ?>" <?= $settings['receipt_payment_method'] === $item ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_receipt_object')) ?></label><select class="form-select" name="receipt_payment_object"><?php foreach ($objectLabels as $item => $label): ?><option value="<?= $item ?>" <?= $settings['receipt_payment_object'] === $item ? 'selected' : '' ?>><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></option><?php endforeach; ?></select></div>
        </div>
        <hr class="my-4">
        <h2 class="h5 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_offer_settings')) ?></h2>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="subscriptions-offer-page"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_offer_page')) ?></label>
                <select class="form-select" id="subscriptions-offer-page" name="public_offer_page_id" data-select>
                    <option value="0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_offer_use_url')) ?></option>
                    <?php foreach (($offer_pages ?? []) as $offerPage): ?><option value="<?= (int)$offerPage['id'] ?>" <?= (int)$settings['public_offer_page_id'] === (int)$offerPage['id'] ? 'selected' : '' ?>><?= htmlSC((string)$offerPage['title']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="subscriptions-offer-url"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_offer_url')) ?></label>
                <input class="form-control" id="subscriptions-offer-url" name="public_offer_url" value="<?= htmlSC((string)$settings['public_offer_url']) ?>" placeholder="https://example.ru/offer.pdf">
            </div>
            <div class="col-12"><p class="form-text mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_offer_settings_hint')) ?></p></div>
        </div>
        <?php foreach (['result_url' => 'subscriptions_result_url', 'success_url' => 'subscriptions_success_url', 'fail_url' => 'subscriptions_fail_url'] as $key => $label): ?><div class="mb-3"><label class="form-label"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></label><input class="form-control font-monospace" value="<?= htmlSC((string)$settings[$key]) ?>" readonly></div><?php endforeach; ?>
        <button class="btn btn-dark rounded-pill" type="submit" name="save_robokassa_settings" value="1"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_save')) ?></button>
    </form>

    <?php $addressStats = (array)($address_catalog_stats ?? []); ?>
    <div class="card border-0 shadow-sm mt-4" data-local-address-catalog>
        <div class="card-body p-4">
            <h2 class="h5 mb-2">Локальный справочник адресов</h2>
            <p class="text-body-secondary mb-2">
                Города, улицы и дома ищутся только в локальной базе FIREBALL CMS.
                Внешние API и платные сервисы не используются.
            </p>
            <p class="small fw-semibold mb-3">
                Записей: <?= (int)($addressStats['rows'] ?? 0) ?>
                · городов: <?= (int)($addressStats['cities'] ?? 0) ?>
                · улиц: <?= (int)($addressStats['streets'] ?? 0) ?>
            </p>

            <form
                method="post"
                action="<?= htmlSC(base_href('/admin/subscriptions/address-catalog/import')) ?>"
                class="row g-3 align-items-end"
                data-address-import
                data-batch-url="<?= htmlSC(base_href('/admin/subscriptions/address-catalog/batch')) ?>"
                data-request-limit="<?= (int)($address_import_limit ?? 262144) ?>"
            >
                <?= get_csrf_field() ?>
                <div class="col-lg-7">
                    <label class="form-label">CSV-файл справочника</label>
                    <?php // No name: the file must never become part of a native form POST if the loader fails. ?>
                    <input class="form-control" type="file" accept=".csv,.txt,.tsv,text/csv,text/plain" required disabled>
                    <div class="form-text">
                        Колонки: region, city, street, house, postal_code.
                        Обязательны region и city.
                        Большой CSV загружается небольшими порциями. Кодировка — UTF-8.
                    </div>
                </div>
                <div class="col-lg-3">
                    <label class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="replace_catalog" value="1" disabled>
                        <span class="form-check-label">Заменить текущий справочник</span>
                    </label>
                </div>
                <div class="col-lg-2">
                    <button class="btn btn-dark w-100" type="submit" disabled>Импортировать</button>
                </div>
                <div class="col-12" data-import-unavailable role="status">
                    <div class="alert alert-warning mb-0">Загрузчик CSV ещё не готов. Если кнопка «Импортировать» не становится доступной, обновите страницу и проверьте, что JavaScript включён.</div>
                </div>
                <div class="col-12" data-import-progress hidden>
                    <div class="progress mb-2" role="progressbar" aria-label="Импорт адресов" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                    <p class="small mb-2" data-import-status role="status" aria-live="polite"></p>
                    <div class="alert alert-danger mb-2" data-import-error role="alert" hidden></div>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-import-resume hidden>Продолжить импорт</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-import-pause hidden>Приостановить</button>
                    <button class="btn btn-outline-danger btn-sm" type="button" data-import-cancel hidden>Отменить импорт</button>
                </div>
                <div class="col-12"><p class="form-text mb-0">Не закрывайте страницу до завершения. При замене старый справочник доступен до окончания импорта; при добавлении готовые порции сохраняются сразу.</p></div>
                <noscript><div class="col-12 alert alert-warning">Для импорта справочника включите JavaScript: файл загружается небольшими порциями, а не целиком.</div></noscript>
            </form>

            <?php if ((int)($addressStats['rows'] ?? 0) > 0): ?>
                <form
                    method="post"
                    action="<?= htmlSC(base_href('/admin/subscriptions/address-catalog/clear')) ?>"
                    class="mt-3"
                >
                    <?= get_csrf_field() ?>
                    <button class="btn btn-outline-danger btn-sm" type="submit">
                        Очистить справочник
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="<?= htmlSC(base_href('/plugins/subscriptions/assets/address-catalog-import.js?v=' . filemtime(__DIR__ . '/../../assets/address-catalog-import.js'))) ?>" defer></script>
<?php require __DIR__ . '/shell-close.php'; ?>
