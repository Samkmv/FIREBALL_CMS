<?php if ($total>50): ?>
<nav class="d-flex align-items-center gap-3 mt-3" aria-label="Страницы альбомов">
    <?php $queryParams=request()->get; ?>
    <?php if ($page>1): $queryParams['page']=$page-1; ?><a class="btn btn-outline-dark" href="<?= htmlSC(base_href($pagination_path).'?'.http_build_query($queryParams)) ?>">Назад</a><?php endif; ?>
    <span>Страница <?= (int)$page ?> из <?= (int)ceil($total/50) ?></span>
    <?php if ($page*50<$total): $queryParams['page']=$page+1; ?><a class="btn btn-outline-dark" href="<?= htmlSC(base_href($pagination_path).'?'.http_build_query($queryParams)) ?>">Далее</a><?php endif; ?>
</nav>
<?php endif; ?>
