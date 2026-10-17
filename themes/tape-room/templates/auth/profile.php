<section class="card tr-card tr-profile-card">
    <?= $this->partial('account-navigation',['section'=>'profile']) ?>
    <header class="tr-profile-heading"><img class="tr-profile-avatar" src="<?= htmlSC(get_user_avatar($user['avatar']??null,'lg')) ?>" alt="Аватар профиля" width="88" height="88"><div><span class="tr-eyebrow">ВАШ АККАУНТ</span><h1><?= htmlSC($user['name'] ?? 'Ваш аккаунт') ?></h1><p>@<?= htmlSC($user['login']??'') ?></p><p><?= htmlSC($user['email'] ?? '') ?></p></div></header>
    <div class="tr-profile-sync"><?= $this->partial('site-icon',['name'=>'repeat']) ?><p><strong>Ваша музыка — на всех устройствах</strong><span>Песня, очередь и позиция сохраняются в аккаунте. На другом устройстве нажмите воспроизведение, чтобы продолжить.</span></p></div>
    <div class="tr-profile-actions"><a class="btn btn-primary" href="<?= htmlSC(base_href('/')) ?>"><?= $this->partial('site-icon',['name'=>'music']) ?>Открыть Tape Room</a></div>
    <form class="tr-profile-logout" action="<?= htmlSC(base_href('/logout')) ?>" method="post">
        <?= get_csrf_field() ?><button class="btn btn-outline-secondary" type="submit">Выйти из аккаунта</button>
    </form>
</section>
