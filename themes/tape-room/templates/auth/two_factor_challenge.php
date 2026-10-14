<section class="tr-card">
    <h1>Подтвердите вход</h1><p>Введите код из приложения аутентификации или резервный код.</p>
    <?= $this->partial('form-errors') ?>
    <form class="tr-form" action="<?= htmlSC(base_href('/two-factor-challenge')) ?>" method="post">
        <?= get_csrf_field() ?>
        <?= $this->partial('field', ['name' => 'code', 'label' => 'Код подтверждения', 'autocomplete' => 'one-time-code']) ?>
        <button class="rp-button rp-button--primary" type="submit">Продолжить</button>
    </form>
    <div class="tr-links"><a href="<?= htmlSC(base_href('/login')) ?>">Вернуться ко входу</a><a href="<?= htmlSC(base_href('/two-factor-recovery')) ?>">Восстановить доступ</a></div>
</section>
