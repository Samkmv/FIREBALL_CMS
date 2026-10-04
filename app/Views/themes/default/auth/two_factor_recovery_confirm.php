<section class="container py-5">
    <div class="row justify-content-center"><div class="col-md-8 col-lg-5">
        <div class="border rounded-5 p-4">
            <h1 class="h3"><?php print_translation('auth_two_factor_recovery_title') ?></h1>
            <p><?php print_translation('auth_two_factor_recovery_confirm_description') ?></p>
            <form action="<?= htmlSC(base_href('/two-factor-recovery/reset')) ?>" method="post">
                <?= get_csrf_field() ?>
                <input type="hidden" name="token" value="<?= htmlSC($token) ?>">
                <button class="btn btn-dark w-100" type="submit"><?php print_translation('auth_two_factor_recovery_confirm_submit') ?></button>
            </form>
        </div>
    </div></div>
</section>
