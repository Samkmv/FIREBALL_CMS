<?php
$avatarDemo=!empty($demo);$avatarUser=$user??[];$avatarId=$avatarDemo?'tr-demo-avatar':'tr-account-avatar';
$avatarErrors=$avatarDemo?[]:(array)((session()->get('form_errors',[])?:[])['avatar_file']??[]);
$avatarMax=min(5*1048576,\App\Services\UploadPolicy::limits()['effective']);
?>
<form class="tr-avatar-editor" <?= $avatarDemo?'data-tr-demo-form':'action="'.htmlSC(base_href('/profile/settings?section=information')).'"' ?> method="post" enctype="multipart/form-data" data-tr-avatar-editor data-max-bytes="<?= $avatarMax ?>">
    <?php if(!$avatarDemo): ?><?= get_csrf_field() ?><input type="hidden" name="profile_action" value="avatar"><?php endif; ?>
    <div class="tr-avatar-preview"><img data-tr-avatar-preview src="<?= htmlSC(get_user_avatar($avatarUser['avatar']??null,'lg')) ?>" alt="Аватар профиля" width="88" height="88"></div>
    <div class="tr-avatar-fields"><h2>Аватар профиля</h2><p>Выберите фотографию или изображение для своего аккаунта.</p>
        <div data-tr-file-picker class="tr-avatar-picker"><input class="visually-hidden" id="<?= $avatarId ?>" data-tr-avatar-file type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Выбрать аватар" aria-describedby="<?= $avatarId ?>-hint <?= $avatarId ?>-error" required><button class="btn btn-outline-secondary" type="button" data-tr-file-pick aria-controls="<?= $avatarId ?>"><?= $this->partial('site-icon',['name'=>'upload']) ?><span>Выбрать изображение</span></button><span data-tr-file-name aria-live="polite">Файл не выбран</span></div>
        <small id="<?= $avatarId ?>-hint">JPG, PNG, WebP или GIF · до <?= round($avatarMax/1048576,1) ?> МБ</small>
        <p class="tr-avatar-error" id="<?= $avatarId ?>-error" data-tr-avatar-error role="alert" <?= $avatarErrors?'':'hidden' ?>><?= htmlSC(implode(' ',array_map('strval',$avatarErrors))) ?></p>
        <button class="btn btn-primary" type="submit" data-tr-avatar-save disabled><?= $this->partial('site-icon',['name'=>'check']) ?><span>Сохранить аватар</span></button>
    </div>
</form>
