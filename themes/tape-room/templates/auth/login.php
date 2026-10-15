<div class="row g-4 g-xl-5 align-items-center tr-auth-layout">
    <?= $this->partial('auth-intro') ?>
    <section class="col-lg-6">
        <div class="card tr-auth-card mx-auto"><div class="card-body p-4 p-sm-5">
            <span class="tr-eyebrow">С ВОЗВРАЩЕНИЕМ</span>
            <h1 class="tr-auth-title">Войти в Tape Room</h1>
            <p class="text-body-secondary mb-4">Ваша музыка уже ждёт вас.</p>
            <?= $this->partial('form-errors') ?>
            <form class="vstack gap-3" action="<?= htmlSC(base_href('/login')) ?>" method="post">
                <?= get_csrf_field() ?>
                <?= $this->partial('field',['name'=>'login','label'=>'Логин','autocomplete'=>'username']) ?>
                <?= $this->partial('field',['name'=>'password','label'=>'Пароль','type'=>'password','autocomplete'=>'current-password']) ?>
                <?php if(!empty($password_recovery_available)): ?><div class="text-end"><button type="button" class="btn btn-link p-0 tr-form-link" data-bs-toggle="modal" data-bs-target="#tr-password-recovery">Забыли пароль?</button></div><?php endif; ?>
                <button class="btn btn-primary btn-lg w-100 mt-2" type="submit">Войти <?= $this->partial('site-icon',['name'=>'arrow']) ?></button>
            </form>
            <div class="tr-auth-switch">Ещё нет аккаунта? <a href="<?= htmlSC(base_href('/register')) ?>">Создать аккаунт</a></div>
        </div></div>
    </section>
</div>
<?php if(!empty($password_recovery_available)): ?>
<div class="modal fade" id="tr-password-recovery" tabindex="-1" aria-labelledby="tr-password-recovery-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-4" id="tr-password-recovery-title">Восстановить доступ</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"><?= $this->partial('site-icon',['name'=>'close']) ?></button></div><form action="<?= htmlSC(base_href('/forgot-password')) ?>" method="post"><div class="modal-body"><p class="text-body-secondary">Введите почту, указанную при регистрации.</p><?= get_csrf_field() ?><?= $this->partial('field',['name'=>'reset_email','label'=>'Электронная почта','type'=>'email','autocomplete'=>'email']) ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn btn-primary">Восстановить пароль</button></div></form></div></div></div>
<?php endif; ?>
