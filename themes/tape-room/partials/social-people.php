<?php if(empty($people)): ?><div class="tr-social-empty"><h2>Пока никого нет</h2><p>Попробуйте другое имя или загляните сюда позже.</p></div><?php else: ?>
<div class="row g-3 tr-people-grid">
<?php foreach($people as $person): ?><div class="col-12 col-md-6 col-xl-4"><article class="card tr-person-card h-100"><div class="card-body">
    <a class="tr-person-card-heading" href="<?= htmlSC($person['url']) ?>"><img src="<?= htmlSC($person['avatar']) ?>" width="64" height="64" alt="" loading="lazy"><h2><?= htmlSC($person['name']) ?></h2></a>
    <?php if($person['bio']!==''): ?><p class="tr-person-card-bio"><?= htmlSC($person['bio']) ?></p><?php endif; ?>
    <p class="tr-person-card-meta">Альбомов: <?= $person['albumsCount'] ?> · Подписчиков: <span data-tr-follow-count="<?= $person['id'] ?>"><?= $person['followersCount'] ?></span></p>
    <div class="tr-person-card-actions"><?= $this->partial('social-follow',['person'=>$person]) ?></div>
</div></article></div><?php endforeach; ?>
</div><?php endif; ?>
