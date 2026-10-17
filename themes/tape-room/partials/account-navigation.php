<?php $accountSection=$section??'profile'; ?>
<nav class="tr-account-tabs nav nav-pills" aria-label="Разделы аккаунта">
    <a class="nav-link <?= $accountSection==='profile'?'active':'' ?>" href="<?= htmlSC(base_href('/profile')) ?>" <?= $accountSection==='profile'?'aria-current="page"':'' ?>><?= $this->partial('site-icon',['name'=>'user']) ?><span>Мой профиль</span></a>
    <a class="nav-link <?= $accountSection==='settings'?'active':'' ?>" href="<?= htmlSC(base_href('/profile/settings')) ?>" <?= $accountSection==='settings'?'aria-current="page"':'' ?>><?= $this->partial('site-icon',['name'=>'settings']) ?><span>Настройки аккаунта</span></a>
</nav>
