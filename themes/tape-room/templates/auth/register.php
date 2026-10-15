<?php
$privacyUrl = '';
$privacyPageId = (int)($settings['cookie_policy_page_id'] ?? 0);
if (($settings['cookie_policy_use_on_registration'] ?? '0') === '1' && $privacyPageId > 0) {
    $privacyPage = (new \App\Models\Page())->findPublishedById($privacyPageId);
    $privacyUrl = (string)($privacyPage['url'] ?? '');
}
if ($privacyUrl === '') {
    foreach (\FBL\Theme::getLegalInformationMenu() as $legalPage) {
        $text = mb_strtolower((string)($legalPage['slug'] ?? '') . ' ' . (string)($legalPage['title'] ?? $legalPage['label'] ?? ''));
        if (str_contains($text, 'privacy') || str_contains($text, 'конфиденциаль') || str_contains($text, 'datenschutz') || str_contains($text, '隐私')) {
            $privacyUrl = (string)($legalPage['url'] ?? $legalPage['href'] ?? '');
            break;
        }
    }
}
?>
<div class="row g-4 g-xl-5 align-items-center tr-auth-layout">
    <?= $this->partial('auth-intro') ?>
    <section class="col-lg-6">
        <div class="card tr-auth-card mx-auto"><div class="card-body p-4 p-sm-5">
            <span class="tr-eyebrow">ВАШ НОВЫЙ РИТМ</span>
            <h1 class="tr-auth-title">Ваша музыкальная комната</h1>
            <p class="text-body-secondary mb-4">Создайте аккаунт и соберите свою коллекцию.</p>
            <?= $this->partial('form-errors') ?>
            <form class="vstack gap-3" action="<?= htmlSC(base_href('/register')) ?>" method="post">
                <?= get_csrf_field() ?>
                <div class="row g-3"><div class="col-sm-6"><?= $this->partial('field',['name'=>'name','label'=>'Имя','autocomplete'=>'name']) ?></div><div class="col-sm-6"><?= $this->partial('field',['name'=>'login','label'=>'Логин','autocomplete'=>'nickname','minlength'=>3,'pattern'=>'[a-zA-Z0-9-]+']) ?></div></div>
                <?= $this->partial('field',['name'=>'email','label'=>'Электронная почта','type'=>'email','autocomplete'=>'email']) ?>
                <?= $this->partial('field',['name'=>'password','label'=>'Пароль','type'=>'password','autocomplete'=>'new-password','minlength'=>8]) ?>
                <?= $this->partial('field',['name'=>'password_confirmation','label'=>'Повторите пароль','type'=>'password','autocomplete'=>'new-password','minlength'=>8]) ?>
                <div class="form-check tr-privacy"><input class="form-check-input" id="tr-privacy" name="privacy_accepted" type="checkbox" value="1" required><label class="form-check-label" for="tr-privacy">Я принимаю <?php if($privacyUrl): ?><a href="<?= htmlSC($privacyUrl) ?>" target="_blank" rel="noopener noreferrer">политику конфиденциальности</a><?php else: ?>политику конфиденциальности<?php endif; ?></label></div>
                <button class="btn btn-primary btn-lg w-100 mt-2" type="submit">Создать аккаунт <?= $this->partial('site-icon',['name'=>'arrow']) ?></button>
            </form>
            <div class="tr-auth-switch">Уже есть аккаунт? <a href="<?= htmlSC(base_href('/login')) ?>">Войти</a></div>
        </div></div>
    </section>
</div>
