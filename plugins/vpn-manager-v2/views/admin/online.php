<?php $t = static fn(string $key): string => FireballPluginVpnManagerV2::t('vpn_manager_v2_online_' . $key); ?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title, 'subtitle' => $subtitle]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<div data-vpn-online
     data-url="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/online/data')) ?>"
     data-failed="<?= htmlSC($t('failed')) ?>"
     data-count-label="<?= htmlSC($t('found')) ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <span class="small text-body-secondary"><?= htmlSC($t('refresh_hint')) ?></span>
        <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button" data-vpn-online-refresh>
            <i class="ci-refresh-cw" aria-hidden="true"></i><span><?= htmlSC($t('refresh')) ?></span>
        </button>
    </div>
    <section class="border rounded-5 p-3 p-md-4 mb-3" aria-label="<?= htmlSC($t('search')) ?>">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-5">
                <label class="form-label small" for="vpnOnlineSearch"><?= htmlSC($t('search')) ?></label>
                <input id="vpnOnlineSearch" type="search" class="form-control" autocomplete="off"
                       placeholder="<?= htmlSC($t('search_hint')) ?>" data-vpn-online-search>
            </div>
            <div class="col-6 col-lg-3">
                <label class="form-label small" for="vpnOnlineScope"><?= htmlSC($t('show')) ?></label>
                <select id="vpnOnlineScope" class="form-select" data-vpn-online-scope>
                    <option value="online"><?= htmlSC($t('now')) ?></option>
                    <option value="all"><?= htmlSC($t('all')) ?></option>
                </select>
            </div>
            <div class="col-6 col-lg-4">
                <label class="form-label small" for="vpnOnlineSort"><?= htmlSC($t('sort')) ?></label>
                <select id="vpnOnlineSort" class="form-select" data-vpn-online-sort-select>
                    <?php foreach (['last_seen', 'user', 'server', 'subscription', 'inbound', 'presence'] as $key): ?>
                        <option value="<?= htmlSC($key) ?>"><?= htmlSC($t($key)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="small text-body-secondary" data-vpn-online-count role="status"></span>
                <button class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button"
                        data-vpn-online-direction data-ascending="<?= htmlSC($t('ascending')) ?>" data-descending="<?= htmlSC($t('descending')) ?>">
                    <i class="ci-arrow-down" aria-hidden="true"></i><span><?= htmlSC($t('descending')) ?></span>
                </button>
            </div>
        </div>
    </section>
    <div class="alert alert-warning rounded-4" data-vpn-online-error role="status" hidden></div>
    <div data-vpn-online-results aria-busy="true">
        <?php require __DIR__ . '/partials/online-results.php'; ?>
    </div>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
