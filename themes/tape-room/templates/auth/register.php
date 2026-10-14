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
<section class="tr-card">
    <h1>Ваша музыкальная комната</h1>
    <p>Создайте аккаунт, чтобы слушать музыку и собирать свою коллекцию.</p>
    <?= $this->partial('form-errors') ?>
    <form class="tr-form" action="<?= htmlSC(base_href('/register')) ?>" method="post">
        <?= get_csrf_field() ?>
        <?= $this->partial('field', ['name' => 'name', 'label' => 'Имя', 'autocomplete' => 'name']) ?>
        <?= $this->partial('field', ['name' => 'login', 'label' => 'Логин', 'autocomplete' => 'nickname', 'minlength' => 3, 'pattern' => '[a-zA-Z0-9-]+']) ?>
        <?= $this->partial('field', ['name' => 'email', 'label' => 'Электронная почта', 'type' => 'email', 'autocomplete' => 'email']) ?>
        <?= $this->partial('field', ['name' => 'password', 'label' => 'Пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
        <?= $this->partial('field', ['name' => 'password_confirmation', 'label' => 'Повторите пароль', 'type' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8]) ?>
        <label class="tr-check"><input name="privacy_accepted" type="checkbox" value="1" required><span>Я принимаю <?php if ($privacyUrl): ?><a href="<?= htmlSC($privacyUrl) ?>" target="_blank" rel="noopener noreferrer">политику конфиденциальности</a><?php else: ?>политику конфиденциальности<?php endif; ?></span></label>
        <button class="rp-button rp-button--primary" type="submit">Создать аккаунт</button>
    </form>
    <div class="tr-links"><a href="<?= htmlSC(base_href('/login')) ?>">Уже есть аккаунт? Войти</a></div>
</section>
