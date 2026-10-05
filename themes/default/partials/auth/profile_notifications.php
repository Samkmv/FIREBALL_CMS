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
            class="badge rounded-pill text-bg-secondary"
            data-pwa-push-status
            role="status" aria-live="polite"
        >
            <?= htmlSC(return_translation($pushStatusKey)) ?>
        </span>
    </div>

    <p class="text-body-secondary mb-3" data-pwa-push-status-hint>
        <?= print_translation($pushReady ? 'auth_profile_push_hint_checking' : 'auth_profile_push_hint_unavailable') ?>
    </p>

    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-dark rounded-pill d-none align-items-center gap-2" type="button" data-pwa-enable-push disabled>
            <i class="ci-bell"></i>
            <span><?= print_translation('auth_profile_push_enable') ?></span>
        </button>
        <button class="btn btn-outline-secondary rounded-pill d-none align-items-center gap-2" type="button" data-pwa-disable-push disabled>
            <i class="ci-bell-off"></i>
            <span><?= print_translation('auth_profile_push_disable') ?></span>
        </button>
    </div>
    <p class="text-danger mb-0 mt-3 d-none" data-pwa-push-feedback role="status" aria-live="polite"></p>
</div>
