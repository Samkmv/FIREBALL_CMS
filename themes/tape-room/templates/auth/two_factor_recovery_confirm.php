<section class="card tr-card">
    <h1>Восстановить доступ</h1><p>Подтвердите сброс двухфакторной аутентификации. После этого вы сможете войти с паролем.</p>
    <form class="tr-form" action="<?= htmlSC(base_href('/two-factor-recovery/reset')) ?>" method="post">
        <?= get_csrf_field() ?><input type="hidden" name="token" value="<?= htmlSC($token) ?>">
        <button class="btn btn-primary" type="submit">Подтвердить сброс</button>
    </form>
</section>
