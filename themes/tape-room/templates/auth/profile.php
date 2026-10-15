<section class="card tr-card">
    <h1><?= htmlSC($user['name'] ?? 'Ваш аккаунт') ?></h1><p><?= htmlSC($user['email'] ?? '') ?></p>
    <div class="tr-links"><a class="btn btn-primary" href="<?= htmlSC(base_href('/')) ?>">Открыть Tape Room</a><a href="<?= htmlSC(base_href('/profile/settings')) ?>">Настройки аккаунта</a></div>
    <form class="tr-form" action="<?= htmlSC(base_href('/logout')) ?>" method="post">
        <?= get_csrf_field() ?><button class="btn btn-outline-secondary" type="submit">Выйти из аккаунта</button>
    </form>
</section>
