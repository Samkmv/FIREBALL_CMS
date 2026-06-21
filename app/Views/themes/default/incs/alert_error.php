<div class="toast app-toast--error border-danger fade show" role="alert" aria-live="assertive" aria-atomic="true" data-auto-dismiss-alert data-auto-dismiss-delay="5000">
    <div class="d-flex align-items-start">
        <i class="ci-banned text-danger fs-base mt-1 me-2"></i>
        <div class="toast-body me-2">
            <?= htmlSC($flash_error ?? '') ?>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>
