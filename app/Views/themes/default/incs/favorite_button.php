<?php
$favoriteReady = (bool)($post['favorite_ready'] ?? $favorite_ready ?? false);
$favoriteSaved = (bool)($post['favorite_saved'] ?? $favorite_saved ?? false);
$favoriteLabel = return_translation($favoriteSaved ? 'account_favorite_remove' : 'account_favorite_add');
$favoriteClass = 'btn btn-icon btn-outline-secondary rounded-circle post-favorite__button';
?>
<?php if ((int)($post['id'] ?? 0) > 0): ?>
    <div class="post-favorite">
        <?php if (!check_auth()): ?>
            <a class="<?= $favoriteClass ?>" href="<?= base_href('/login') ?>" title="<?= htmlSC(return_translation('account_favorite_login')) ?>" aria-label="<?= htmlSC(return_translation('account_favorite_login')) ?>"><i class="ci-heart" aria-hidden="true"></i></a>
        <?php elseif (!$favoriteReady): ?>
            <button class="<?= $favoriteClass ?>" type="button" disabled title="<?= htmlSC(return_translation('account_feature_unavailable')) ?>" aria-label="<?= htmlSC(return_translation('account_feature_unavailable')) ?>"><i class="ci-heart" aria-hidden="true"></i></button>
        <?php else: ?>
            <form action="<?= base_href('/profile/favorites/' . ($favoriteSaved ? 'remove' : 'add')) ?>" method="post" data-favorite-form data-add-url="<?= base_href('/profile/favorites/add') ?>" data-remove-url="<?= base_href('/profile/favorites/remove') ?>" data-add-label="<?= htmlSC(return_translation('account_favorite_add')) ?>" data-remove-label="<?= htmlSC(return_translation('account_favorite_remove')) ?>" data-error="<?= htmlSC(return_translation('account_favorite_error')) ?>">
                <?= get_csrf_field() ?><input type="hidden" name="entity_type" value="post"><input type="hidden" name="entity_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return_to" value="post">
                <button class="<?= $favoriteClass ?><?= $favoriteSaved ? ' text-danger' : '' ?>" type="submit" title="<?= htmlSC($favoriteLabel) ?>" aria-label="<?= htmlSC($favoriteLabel) ?>" aria-pressed="<?= $favoriteSaved ? 'true' : 'false' ?>"><i class="<?= $favoriteSaved ? 'ci-heart-filled' : 'ci-heart' ?>" data-favorite-icon aria-hidden="true"></i><span class="visually-hidden" data-favorite-label><?= print_translation($favoriteSaved ? 'account_favorite_saved' : 'account_favorite_add') ?></span></button>
            </form>
            <div class="alert alert-danger post-favorite__error mb-0" data-favorite-error role="alert" hidden></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
