<section class="tr-card">
    <h1>Войти в Tape Room</h1>
    <p>Ваша музыка, альбомы и личные коллекции.</p>
    <?= $this->partial('form-errors') ?>
    <form class="tr-form" action="<?= htmlSC(base_href('/login')) ?>" method="post">
        <?= get_csrf_field() ?>
        <?= $this->partial('field', ['name' => 'login', 'label' => 'Логин', 'autocomplete' => 'username']) ?>
        <?= $this->partial('field', ['name' => 'password', 'label' => 'Пароль', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
        <button class="rp-button rp-button--primary" type="submit">Войти</button>
    </form>
    <div class="tr-links"><a href="<?= htmlSC(base_href('/register')) ?>">Создать аккаунт</a></div>
    <?php if (!empty($password_recovery_available)): ?>
        <details class="tr-links"><summary>Забыли пароль?</summary>
            <form class="tr-form" action="<?= htmlSC(base_href('/forgot-password')) ?>" method="post">
                <?= get_csrf_field() ?>
                <?= $this->partial('field', ['name' => 'reset_email', 'label' => 'Электронная почта', 'type' => 'email', 'autocomplete' => 'email']) ?>
                <button class="rp-button" type="submit">Восстановить пароль</button>
            </form>
        </details>
    <?php endif; ?>
</section>
