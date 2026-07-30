<div class="toast app-toast--info border-info fade show" role="alert" aria-live="assertive" aria-atomic="true" data-auto-dismiss-alert data-auto-dismiss-delay="5000">
    <div class="d-flex align-items-start">
        <i class="ci-info text-info fs-base mt-1 me-2"></i>
        <div class="toast-body me-2">
            <strong class="d-block mb-1"><?= print_translation('toast_info_title') ?></strong>
            <span><?= htmlSC($flash_info ?? '') ?></span>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="<?= htmlSC(return_translation('admin_btn_close')) ?>"></button>
    </div>
</div>
