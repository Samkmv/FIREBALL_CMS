<?php
$t = static fn(string $key): string => FireballPluginVpnManagerV2::t('vpn_manager_v2_online_' . $key);
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<div data-vpn-online
     data-url="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/online/data')) ?>"
     data-failed="<?= htmlSC($t('failed')) ?>"
     data-count-label="<?= htmlSC($t('found')) ?>">
    <div class="alert alert-warning" data-vpn-online-error role="status" hidden></div>
    <div data-vpn-online-overview>
        <?php require __DIR__ . '/partials/online-summary.php'; ?>
    </div>
    <section aria-labelledby="vpnOnlineList">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h5 mb-0" id="vpnOnlineList"><?= htmlSC($t('table_title')) ?></h2>
            <span class="small text-body-secondary d-none d-md-inline ms-auto" id="vpnOnlineRefreshHint"><?= htmlSC($t('refresh_hint')) ?></span>
            <button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2" type="button"
                    data-vpn-online-refresh aria-describedby="vpnOnlineRefreshHint">
                <i class="ci-refresh-cw" aria-hidden="true"></i><span><?= htmlSC($t('refresh')) ?></span>
            </button>
        </div>
        <div class="row g-2 align-items-end mb-2">
            <div class="col-12 col-xl">
                <label class="visually-hidden" for="vpnOnlineSearch"><?= htmlSC($t('search')) ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-transparent"><i class="ci-search" aria-hidden="true"></i></span>
                    <input id="vpnOnlineSearch" type="search" class="form-control" autocomplete="off"
                           placeholder="<?= htmlSC($t('search_hint')) ?>" data-vpn-online-search>
                </div>
            </div>
            <div class="col-12 col-sm col-xl-auto">
                <label class="visually-hidden" for="vpnOnlineScope"><?= htmlSC($t('show')) ?></label>
                <select id="vpnOnlineScope" class="form-select" data-vpn-online-scope>
                    <option value="online"><?= htmlSC($t('now')) ?></option>
                    <option value="all"><?= htmlSC($t('all')) ?></option>
                </select>
            </div>
            <div class="col-12 col-sm col-xl-auto">
                <label class="visually-hidden" for="vpnOnlineSort"><?= htmlSC($t('sort')) ?></label>
                <div class="input-group">
                    <select id="vpnOnlineSort" class="form-select" data-vpn-online-sort-select>
                        <?php foreach (['last_seen', 'user', 'server', 'subscription', 'inbound', 'presence'] as $key): ?>
                            <option value="<?= htmlSC($key) ?>"><?= htmlSC($t($key)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary" type="button" data-vpn-online-direction
                            data-ascending="<?= htmlSC($t('ascending')) ?>" data-descending="<?= htmlSC($t('descending')) ?>"
                            aria-label="<?= htmlSC($t('descending')) ?>" title="<?= htmlSC($t('descending')) ?>">
                        <i class="ci-arrow-down" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
        <p class="small text-body-secondary mb-2" data-vpn-online-count role="status" hidden></p>
        <div data-vpn-online-results aria-busy="true">
            <p class="text-body-secondary small" data-vpn-online-loading>
                <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><?= htmlSC($t('loading')) ?>
            </p>
        </div>
    </section>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
