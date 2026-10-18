<?php $searchDemo=!empty($demo);$searchId=$searchDemo?'ui-people-search':'tr-people-search'; ?>
<form class="tr-social-search" <?= $searchDemo?'data-tr-demo-form':'data-tr-social-search' ?> method="get" action="<?= htmlSC(base_href('/tape-room/people')) ?>">
    <input type="hidden" name="list" value="<?= htmlSC($search['list']??'all') ?>"><label class="visually-hidden" for="<?= $searchId ?>">Найти пользователя</label>
    <input class="form-control" type="search" name="q" maxlength="80" id="<?= $searchId ?>" value="<?= htmlSC($search['query']??'') ?>" placeholder="Найти пользователя по имени">
    <button class="btn btn-outline-secondary" type="submit"><?= $this->partial('site-icon',['name'=>'search']) ?><span>Найти</span></button>
</form>
<p class="tr-social-total">Найдено: <?= (int)($search['total']??0) ?></p>
