<section class="card tr-card tr-settings-card">
    <header class="tr-settings-heading"><span class="tr-eyebrow">ВАШ АККАУНТ</span><h1>Настройки аккаунта</h1><p>Личные данные и безопасность входа.</p></header>
    <?= $this->partial('form-errors') ?>
    <form class="tr-form tr-settings-form" action="<?= htmlSC(base_href('/profile/settings')) ?>" method="post">
        <?= get_csrf_field() ?><input type="hidden" name="profile_action" value="details">
        <?= $this->partial('field', ['name' => 'name', 'label' => 'Имя', 'value' => $user['name'], 'autocomplete' => 'name']) ?>
        <?= $this->partial('field', ['name' => 'login', 'label' => 'Логин', 'value' => $user['login'], 'autocomplete' => 'username']) ?>
        <?= $this->partial('field', ['name' => 'email', 'label' => 'Электронная почта', 'value' => $user['email'], 'type' => 'email', 'autocomplete' => 'email']) ?>
        <div class="tr-settings-actions"><button class="btn btn-primary" type="submit">Сохранить изменения</button></div>
    </form>
    <?php $securityOpen=(string)request()->get('section','')==='security'; ?>
    <div class="accordion tr-settings-accordion" id="tr-security-accordion"><div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button <?= $securityOpen?'':'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#tr-password-change" aria-expanded="<?= $securityOpen?'true':'false' ?>" aria-controls="tr-password-change"><?= $this->partial('site-icon',['name'=>'shield']) ?><span><strong>Изменить пароль</strong><small>Защитите доступ к своему аккаунту</small></span><?= $this->partial('site-icon',['name'=>'chevron-down']) ?></button></h2>
        <div class="accordion-collapse collapse <?= $securityOpen?'show':'' ?>" id="tr-password-change" data-bs-parent="#tr-security-accordion"><div class="accordion-body">
            <form class="tr-form tr-settings-form" action="<?= htmlSC(base_href('/profile/settings?section=security')) ?>" method="post">
                <?= get_csrf_field() ?><input type="hidden" name="profile_action" value="password">
                <?= $this->partial('field', ['name' => 'current_password', 'label' => 'Текущий пароль', 'type' => 'password', 'autocomplete' => 'current-password']) ?>
                <?= $this->partial('field', ['name' => 'password', 'label' => 'Новый пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
                <?= $this->partial('field', ['name' => 'password_confirmation', 'label' => 'Повторите пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
                <div class="tr-settings-actions"><button class="btn btn-primary" type="submit">Обновить пароль</button></div>
            </form>
        </div></div>
    </div></div>
</section>
