<section class="card tr-card">
    <h1>Новый пароль</h1>
    <?= $this->partial('form-errors') ?>
    <form class="tr-form" action="<?= htmlSC(base_href('/reset-password')) ?>" method="post">
        <?= get_csrf_field() ?><input type="hidden" name="token" value="<?= htmlSC($token) ?>">
        <?= $this->partial('field', ['name' => 'password', 'label' => 'Новый пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
        <?= $this->partial('field', ['name' => 'password_confirmation', 'label' => 'Повторите пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
        <button class="btn btn-primary" type="submit">Сохранить пароль</button>
    </form>
</section>
