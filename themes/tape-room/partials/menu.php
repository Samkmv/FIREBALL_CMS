<?php if (check_auth()): ?>
<nav class="tr-nav" aria-label="Tape Room">
    <a href="<?= htmlSC(base_href('/')) ?>">Музыкальная комната</a>
    <a href="<?= htmlSC(base_href('/profile')) ?>">Аккаунт</a>
    <?php if (check_admin()): ?><a href="<?= htmlSC(base_href('/admin')) ?>">Панель управления</a><?php endif; ?>
</nav>
<?php endif; ?>
