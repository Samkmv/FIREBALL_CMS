<section class="tr-social-page">
    <header class="tr-social-page-heading"><div><span class="tr-eyebrow">TAPE ROOM · СООБЩЕСТВО</span><h1>Пользователи</h1><p>Знакомьтесь со слушателями и находите музыку в их профилях.</p></div><?php if(check_auth()): ?><a class="btn btn-outline-secondary" href="<?= htmlSC(base_href('/tape-room/people/'.(int)get_user()['id'])) ?>"><?= $this->partial('site-icon',['name'=>'user']) ?>Мой музыкальный профиль</a><?php endif; ?></header>
    <nav class="nav nav-pills tr-social-tabs" aria-label="Разделы сообщества"><a class="nav-link <?= $people['list']==='all'?'active':'' ?>" href="<?= htmlSC(base_href('/tape-room/people')) ?>" <?= $people['list']==='all'?'aria-current="page"':'' ?>>Все пользователи</a><?php if(check_auth()): ?><a class="nav-link <?= $people['list']==='following'?'active':'' ?>" href="<?= htmlSC(base_href('/tape-room/people?list=following')) ?>" <?= $people['list']==='following'?'aria-current="page"':'' ?>>Мои подписки</a><?php endif; ?></nav>
    <?= $this->partial('social-search',['search'=>$people]) ?>
    <?= $this->partial('social-people',['people'=>$people['people']]) ?>
    <?= $this->partial('social-pagination',['pagination'=>$people,'page_base'=>base_href('/tape-room/people'),'page_query'=>['list'=>$people['list'],'q'=>$people['query']]]) ?>
</section>
