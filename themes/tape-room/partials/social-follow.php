<?php $followPerson=$person; ?>
<?php if($followPerson['mine']): ?>
<a class="btn btn-outline-secondary" href="<?= htmlSC(base_href('/profile/settings#social-settings')) ?>"><?= $this->partial('site-icon',['name'=>'settings']) ?>Настроить профиль</a>
<?php elseif(!check_auth()): ?>
<a class="btn btn-outline-secondary" href="<?= htmlSC(base_href('/login')) ?>"><?= $this->partial('site-icon',['name'=>'follow']) ?>Войти, чтобы подписаться</a>
<?php else: ?>
<form method="post" action="<?= htmlSC(base_href('/tape-room/people/'.$followPerson['id'].'/follow')) ?>" data-tr-social-form data-tr-follow-user="<?= $followPerson['id'] ?>" data-tr-follow-name="<?= htmlSC($followPerson['name']) ?>">
    <?= get_csrf_field() ?><input type="hidden" name="following" value="<?= $followPerson['following']?'0':'1' ?>">
    <button class="btn <?= $followPerson['following']?'btn-secondary is-following':'btn-primary' ?>" type="submit" aria-pressed="<?= $followPerson['following']?'true':'false' ?>" aria-label="<?= htmlSC(($followPerson['following']?'Отписаться от ':'Подписаться на ').$followPerson['name']) ?>"><?= $this->partial('site-icon',['name'=>$followPerson['following']?'followed':'follow']) ?><span data-tr-follow-label><?= $followPerson['following']?'Вы подписаны':'Подписаться' ?></span></button>
    <span class="tr-social-feedback" data-tr-social-feedback role="status" aria-live="polite"></span>
</form>
<?php endif; ?>
