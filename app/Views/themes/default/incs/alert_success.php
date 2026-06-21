<div class="toast app-toast--success border-success fade show" role="alert" aria-live="assertive" aria-atomic="true" data-auto-dismiss-alert data-auto-dismiss-delay="5000">
    <div class="d-flex align-items-start">
        <i class="ci-check-circle text-success fs-base mt-1 me-2"></i>
        <div class="toast-body me-2">
            <?= htmlSC($flash_success ?? '') ?>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>
