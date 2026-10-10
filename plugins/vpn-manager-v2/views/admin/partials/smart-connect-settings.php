<?php
$smartT = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_' . $key));
$smartSelect = static function (string $key, string $id, array $options) use ($settings, $smartT): void { ?>
    <select class="form-select" id="<?= htmlSC($id) ?>" name="<?= htmlSC($key) ?>">
        <?php foreach ($options as $value => $label): ?>
            <option value="<?= htmlSC($value) ?>" <?= ($settings[$key] ?? '') === $value ? 'selected' : '' ?>><?= $smartT($label) ?></option>
        <?php endforeach; ?>
    </select>
<?php }; ?>
<section class="border rounded-5 p-3 p-md-4 mb-4" id="smart-connect" data-vpn-v2-smart-settings>
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ci-refresh-cw fs-4 text-body-secondary" aria-hidden="true"></i>
        <h2 class="h5 mb-0">Smart Connect</h2>
    </div>
    <p class="text-body-secondary mb-4"><?= $smartT('intro') ?></p>
    <div class="row g-3">
        <div class="col-12"><?= $switch('smart_connect_enabled', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_enabled')) ?></div>
        <div class="col-12 col-lg-6">
            <label class="form-label" for="vpnSmartMode"><?= $smartT('mode') ?></label>
            <?php $smartSelect('smart_connect_mode', 'vpnSmartMode', ['manual' => 'manual', 'smart' => 'automatic', 'failover' => 'failover']); ?>
            <div class="form-text"><?= $smartT('mode_help') ?></div>
        </div>
        <div class="col-12 col-lg-6">
            <label class="form-label" for="vpnSmartUrl"><?= $smartT('test_url') ?></label>
            <input class="form-control" type="url" name="smart_connect_test_url" id="vpnSmartUrl" maxlength="512" required
                value="<?= htmlSC((string)($settings['smart_connect_test_url'] ?? 'https://www.gstatic.com/generate_204')) ?>">
            <div class="form-text"><?= $smartT('test_url_help') ?></div>
        </div>
    </div>

    <div class="border rounded-4 p-3 p-md-4 mt-4">
        <h3 class="h6 mb-3">Happ</h3>
        <div class="alert alert-info rounded-4 small mb-3"><?= $smartT('happ_limit') ?></div>
        <div class="row g-3">
            <div class="col-12"><?= $switch('smart_connect_happ_enabled', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_happ_enabled')) ?></div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="vpnSmartProvider">Provider ID</label>
                <input class="form-control font-monospace" id="vpnSmartProvider" name="smart_connect_happ_provider_id" type="text" maxlength="128"
                    autocomplete="off" spellcheck="false" value="<?= htmlSC((string)($settings['smart_connect_happ_provider_id'] ?? '')) ?>">
                <div class="form-text"><?= $smartT('provider_help') ?>
                    <a href="https://www.happ.su/main/dev-docs/provider-id" target="_blank" rel="noopener noreferrer"><?= $smartT('documentation') ?></a>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="vpnSmartPing"><?= $smartT('ping_type') ?></label>
                <?php $smartSelect('smart_connect_happ_ping_type', 'vpnSmartPing', ['proxy' => 'proxy_get', 'proxy-head' => 'proxy_head']); ?>
            </div>
            <div class="col-12 col-md-6"><?= $switch('smart_connect_happ_ping_on_open', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_ping_on_open')) ?></div>
            <div class="col-12 col-md-6"><?= $switch('smart_connect_happ_sort_ping', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_sort_ping')) ?></div>
            <div class="col-12"><?= $switch('smart_connect_happ_autoconnect', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_autoconnect'), FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_ping_required')) ?></div>
        </div>
        <p class="small text-body-secondary mt-3 mb-0"><?= $smartT('provider_privacy') ?></p>
    </div>

    <div class="border rounded-4 p-3 p-md-4 mt-4">
        <h3 class="h6 mb-3">sing-box</h3>
        <div class="row g-3">
            <div class="col-12"><?= $switch('smart_connect_singbox_enabled', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_singbox_enabled'), FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_singbox_help')) ?></div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="vpnSmartInterval"><?= $smartT('interval') ?></label>
                <input class="form-control" type="number" id="vpnSmartInterval" name="smart_connect_interval_seconds" min="30" max="1800" required
                    value="<?= (int)($settings['smart_connect_interval_seconds'] ?? 180) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="vpnSmartTolerance"><?= $smartT('tolerance') ?></label>
                <input class="form-control" type="number" id="vpnSmartTolerance" name="smart_connect_tolerance_ms" min="1" max="5000" required
                    value="<?= (int)($settings['smart_connect_tolerance_ms'] ?? 100) ?>">
            </div>
            <div class="col-12 small text-body-secondary"><?= $smartT('stability_help') ?></div>
        </div>
        <?php if (!empty($smartServers)): ?>
            <h4 class="h6 mt-4 mb-1"><?= $smartT('priorities') ?></h4>
            <p class="small text-body-secondary"><?= $smartT('priorities_help') ?></p>
            <div class="row g-3">
                <?php foreach ($smartServers as $smartServer): $smartId = (int)$smartServer['id']; ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-break" for="vpnSmartPriority<?= $smartId ?>">#<?= $smartId ?> · <?= htmlSC($smartServer['name']) ?></label>
                        <input class="form-control" type="number" min="0" max="1000" id="vpnSmartPriority<?= $smartId ?>"
                            name="smart_connect_server_priorities[<?= $smartId ?>]" value="<?= (int)($settings['smart_connect_server_priorities'][$smartId] ?? 1000) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="border rounded-4 p-3 p-md-4 mt-4">
        <h3 class="h6 mb-3"><?= $smartT('health_title') ?></h3>
        <?= $switch('smart_connect_health_enabled', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_health_enabled'), FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_health_help')) ?>
        <p class="small text-body-secondary mt-3 mb-0"><?= $smartT('health_scope') ?></p>
        <?php if (isset($healthReady) && !$healthReady): ?>
            <div class="alert alert-warning rounded-4 mt-3 mb-0"><?= $smartT('migration_required') ?></div>
        <?php elseif (!empty($serverHealth)): ?>
            <div class="row g-3 mt-1">
                <?php foreach ($serverHealth as $smartHealth):
                    $smartSnapshot = json_decode((string)($smartHealth['snapshot_json'] ?? ''), true);
                    $smartSnapshot = is_array($smartSnapshot) ? $smartSnapshot : [];
                ?>
                    <div class="col-12 col-lg-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <span class="fw-semibold text-break"><?= htmlSC($smartHealth['server_name']) ?></span>
                                <span class="badge text-bg-<?= $smartHealth['state'] === 'healthy' ? 'success' : 'warning' ?>"><?= $smartT('health_' . $smartHealth['state']) ?></span>
                            </div>
                            <div class="small text-body-secondary mt-2">
                                <?= $smartT('panel') ?>: <?= ($smartSnapshot['panel'] ?? '') === 'online' ? $smartT('online') : $smartT('unavailable') ?> ·
                                Xray: <?= htmlSC((string)($smartSnapshot['xray'] ?? 'unknown')) ?>
                                <?php if (is_numeric($smartSnapshot['load']['cpu']['percent'] ?? null)): ?>
                                    · CPU: <?= (float)$smartSnapshot['load']['cpu']['percent'] ?>%
                                <?php endif; ?>
                            </div>
                            <?php foreach ((array)($smartSnapshot['ports'] ?? []) as $smartPort): ?>
                                <div class="small text-body-secondary text-break mt-1">
                                    <?= $smartT('port') ?> <?= (int)($smartPort['port'] ?? 0) ?> · <?= htmlSC((string)($smartPort['transport'] ?? '')) ?> ·
                                    TCP: <?= $smartT('tcp_' . (in_array($smartPort['tcp'] ?? '', ['open', 'closed'], true) ? $smartPort['tcp'] : 'unknown')) ?>
                                </div>
                            <?php endforeach; ?>
                            <dl class="small mb-0 mt-2">
                                <dt><?= $smartT('last_check') ?></dt><dd><?= htmlSC((string)$smartHealth['last_check_at']) ?></dd>
                                <dt><?= $smartT('last_success') ?></dt><dd><?= htmlSC((string)($smartHealth['last_success_at'] ?? '—')) ?></dd>
                                <dt><?= $smartT('failures') ?></dt><dd class="mb-0"><?= (int)$smartHealth['consecutive_failures'] ?></dd>
                            </dl>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="small text-body-secondary mt-3"><?= $smartT('health_empty') ?></div>
        <?php endif; ?>
    </div>
</section>
