<?php
$isPlayer=!empty($is_player);
$authenticated=check_auth();
$siteIcon=fn(string $name): string=>$this->partial('site-icon',['name'=>$name]);
$path=(string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
$roomLinks=[['overview','Обзор','grid'],['albums','Альбомы','disc'],['artists','Артисты','artist'],['genres','Жанры','genres'],['new','Новинки','new'],['popular','Популярное','popular'],['mine','Моя музыка','collection']];
?>
<div class="tr-bootstrap tr-site-navigation <?= $isPlayer?'tr-site-navigation--player':'' ?>" data-tr-shell data-player-shell="<?= $isPlayer?'1':'0' ?>" data-bs-theme="light">
    <nav class="navbar tr-navbar" aria-label="Навигация Tape Room">
        <div class="container-fluid px-0 tr-navbar-inner">
            <button class="btn tr-nav-icon tr-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#tr-site-menu" aria-controls="tr-site-menu" aria-label="Открыть меню" title="Открыть меню"><?= $siteIcon('menu') ?></button>
            <a class="navbar-brand tr-wordmark m-0" href="<?= htmlSC(base_href('/')) ?>" <?= $isPlayer?'data-action="catalog-view" data-view="overview"':'' ?>><span class="tr-wordmark-name">TAPE ROOM<small class="tr-beta">БЕТА</small></span><span class="tr-wordmark-caption">ЛИЧНАЯ МУЗЫКАЛЬНАЯ КОМНАТА</span></a>
            <div class="tr-navbar-actions d-flex align-items-center gap-2">
                <div class="dropdown tr-account-dropdown">
                    <button class="btn tr-nav-icon tr-account-toggle" type="button" id="tr-account-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-controls="tr-account-menu" aria-label="Аккаунт" title="Аккаунт"><?= $siteIcon('user') ?></button>
                    <ul class="dropdown-menu dropdown-menu-end tr-account-menu" id="tr-account-menu" aria-labelledby="tr-account-toggle">
                        <li><h2 class="dropdown-header"><?= $authenticated?htmlSC((string)(get_user()['name']??'Аккаунт')):'Аккаунт' ?></h2></li>
                        <?php if ($authenticated): ?>
                            <li><a class="dropdown-item" href="<?= htmlSC(base_href('/profile')) ?>"><?= $siteIcon('user') ?><span>Мой профиль</span></a></li>
                            <li><a class="dropdown-item" href="<?= htmlSC(base_href('/profile/settings')) ?>"><?= $siteIcon('settings') ?><span>Настройки аккаунта</span></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><form action="<?= htmlSC(base_href('/logout')) ?>" method="post"><?= get_csrf_field() ?><button class="dropdown-item" type="submit"><?= $siteIcon('logout') ?><span>Выйти</span></button></form></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="<?= htmlSC(base_href('/login')) ?>"><?= $siteIcon('login') ?><span>Войти</span></a></li>
                            <li><a class="dropdown-item" href="<?= htmlSC(base_href('/register')) ?>"><?= $siteIcon('register') ?><span>Регистрация</span></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <button class="btn tr-nav-icon tr-theme-toggle" type="button" <?= $isPlayer?'data-action="theme"':'data-tr-theme-toggle' ?> data-tr-theme-icons aria-label="Включить тёмную тему" title="Включить тёмную тему"><span data-tr-theme-light><?= $siteIcon('moon') ?></span><span data-tr-theme-dark hidden><?= $siteIcon('sun') ?></span></button>
            </div>
        </div>
    </nav>
    <aside class="offcanvas offcanvas-start tr-side-menu" tabindex="-1" id="tr-site-menu" aria-label="Меню сайта">
        <div class="offcanvas-header"><h2 class="tr-drawer-title">Навигация</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Закрыть меню"><?= $siteIcon('close') ?></button></div>
        <div class="offcanvas-body d-flex flex-column">
            <?php if($isPlayer): ?><?= $this->partial('library-menu',['room_links'=>$roomLinks]) ?><?php else: ?>
            <nav class="nav flex-column tr-side-links" aria-label="Разделы сайта">
                <?php if ($authenticated): foreach ($roomLinks as [$view,$label,$symbol]): ?>
                    <?php if ($isPlayer): ?><button class="nav-link text-start" type="button" data-action="catalog-view" data-view="<?= $view ?>" data-bs-dismiss="offcanvas"><?= $siteIcon($symbol) ?><span><?= $label ?></span></button>
                    <?php else: ?><a class="nav-link" href="<?= htmlSC(base_href('/').'#room='.$view) ?>"><?= $siteIcon($symbol) ?><span><?= $label ?></span></a><?php endif; ?>
                <?php endforeach; else: ?>
                    <a class="nav-link <?= str_ends_with($path,'/login')?'active':'' ?>" href="<?= htmlSC(base_href('/login')) ?>"><?= $siteIcon('login') ?><span>Войти</span></a>
                    <a class="nav-link <?= str_ends_with($path,'/register')?'active':'' ?>" href="<?= htmlSC(base_href('/register')) ?>"><?= $siteIcon('register') ?><span>Создать аккаунт</span></a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php if ($authenticated): ?><div class="tr-menu-divider"></div><nav class="nav flex-column tr-side-links" aria-label="Аккаунт и настройки"><a class="nav-link <?= str_contains($path,'/profile')?'active':'' ?>" href="<?= htmlSC(base_href('/profile')) ?>"><?= $siteIcon('user') ?><span>Аккаунт</span></a>
                <?php if (check_admin()): ?><a class="nav-link <?= str_contains($path,'/tape-room/ui-kit')?'active':'' ?>" href="<?= htmlSC(base_href('/tape-room/ui-kit')) ?>"><?= $siteIcon('grid') ?><span>UI-компоненты</span></a><a class="nav-link" href="<?= htmlSC(base_href('/admin')) ?>"><?= $siteIcon('settings') ?><span>Панель управления</span></a><?php endif; ?>
            </nav><?php endif; ?>
            <div class="tr-menu-preferences mt-auto"><?= $this->partial('player-mode-toggle') ?></div>
            <div class="tr-menu-bottom"><span class="tr-status-dot"></span> СОЗДАНО ДЛЯ ПРОСЛУШИВАНИЯ<p>Ваша музыка. Ваше пространство.</p></div>
        </div>
    </aside>
</div>
