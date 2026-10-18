<?php if(($pagination['pages']??1)>1): $pageUrl=static fn(int $page):string=>$page_base.'?'.http_build_query([...($page_query??[]),'page'=>$page]); ?>
<nav class="tr-social-pagination" aria-label="Страницы списка">
    <?php if($pagination['page']>1): ?><a class="btn btn-outline-secondary" href="<?= htmlSC($pageUrl($pagination['page']-1)) ?>">Назад</a><?php else: ?><span></span><?php endif; ?>
    <span>Страница <?= $pagination['page'] ?> из <?= $pagination['pages'] ?></span>
    <?php if($pagination['page']<$pagination['pages']): ?><a class="btn btn-outline-secondary" href="<?= htmlSC($pageUrl($pagination['page']+1)) ?>">Далее</a><?php else: ?><span></span><?php endif; ?>
</nav><?php endif; ?>
