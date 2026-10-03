<div class="border rounded-5 p-4 p-md-5 mb-4" id="profile-push-notifications">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="ci-bell fs-4"></i>
                <h2 class="h5 mb-0"><?= print_translation('auth_profile_push_heading') ?></h2>
            </div>
            <p class="text-body-secondary mb-0"><?= print_translation('auth_profile_push_subtitle') ?></p>
        </div>
        <span
            class="badge rounded-pill <?= $pushEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>"
            data-pwa-push-status
            data-status-enabled="<?= htmlSC(return_translation('auth_profile_push_status_enabled')) ?>"
            data-status-disabled="<?= htmlSC(return_translation('auth_profile_push_status_disabled')) ?>"
            data-status-unsupported="<?= htmlSC(return_translation('auth_profile_push_status_unsupported')) ?>"
            data-status-permission="<?= htmlSC(return_translation('auth_profile_push_status_permission')) ?>"
            data-status-unavailable="<?= htmlSC(return_translation('auth_profile_push_status_unavailable')) ?>"
        >
            <?= htmlSC(return_translation($pushStatusKey)) ?>
        </span>
    </div>

    <p class="text-body-secondary mb-3" data-pwa-push-status-hint>
        <?= print_translation($pushReady ? 'auth_profile_push_hint' : 'auth_profile_push_unavailable_hint') ?>
    </p>

    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="button" data-pwa-enable-push>
            <i class="ci-bell"></i>
            <span><?= print_translation('auth_profile_push_enable') ?></span>
        </button>
        <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button" data-pwa-disable-push>
            <i class="ci-bell-off"></i>
            <span><?= print_translation('auth_profile_push_disable') ?></span>
        </button>
    </div>
</div>
