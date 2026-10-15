<section class="card tr-card">
    <h1>Настройки аккаунта</h1>
    <?= $this->partial('form-errors') ?>
    <form class="tr-form" action="<?= htmlSC(base_href('/profile/settings')) ?>" method="post">
        <?= get_csrf_field() ?><input type="hidden" name="profile_action" value="details">
        <?= $this->partial('field', ['name' => 'name', 'label' => 'Имя', 'value' => $user['name'], 'autocomplete' => 'name']) ?>
        <?= $this->partial('field', ['name' => 'login', 'label' => 'Логин', 'value' => $user['login'], 'autocomplete' => 'username']) ?>
        <?= $this->partial('field', ['name' => 'email', 'label' => 'Электронная почта', 'value' => $user['email'], 'type' => 'email', 'autocomplete' => 'email']) ?>
        <button class="btn btn-primary" type="submit">Сохранить</button>
    </form>
    <details class="tr-links"><summary>Изменить пароль</summary>
        <form class="tr-form" action="<?= htmlSC(base_href('/profile/settings?section=security')) ?>" method="post">
            <?= get_csrf_field() ?><input type="hidden" name="profile_action" value="password">
            <?= $this->partial('field', ['name' => 'current_password', 'label' => 'Текущий пароль', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
            <?= $this->partial('field', ['name' => 'password', 'label' => 'Новый пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
            <?= $this->partial('field', ['name' => 'password_confirmation', 'label' => 'Повторите пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
            <button class="btn btn-outline-secondary" type="submit">Изменить пароль</button>
        </form>
    </details>
</section>
