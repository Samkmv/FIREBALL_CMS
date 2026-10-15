<section class="card tr-card">
    <h1>Восстановить доступ</h1><p>Отправьте ссылку на почту вашего аккаунта, чтобы восстановить доступ.</p>
    <?= $this->partial('form-errors') ?>
    <?php if (!empty($two_factor_recovery_available)): ?>
        <form class="tr-form" action="<?= htmlSC(base_href('/two-factor-recovery')) ?>" method="post">
            <?= get_csrf_field() ?><button class="btn btn-primary" type="submit">Отправить ссылку</button>
        </form>
    <?php else: ?><p>Восстановление по почте сейчас недоступно. Используйте резервный код.</p><?php endif; ?>
    <div class="tr-links"><a href="<?= htmlSC(base_href('/two-factor-challenge')) ?>">Вернуться к подтверждению</a></div>
</section>
