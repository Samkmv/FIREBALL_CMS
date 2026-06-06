# Мультиязычность

```php
<html lang="<?= htmlSC(current_locale()) ?>">

<?php foreach (available_locales() as $locale): ?>
    <a href="<?= htmlSC($locale['url']) ?>" <?= $locale['active'] ? 'aria-current="page"' : '' ?>>
        <?= htmlSC($locale['title']) ?>
    </a>
<?php endforeach; ?>
```

`switch_locale_url($code)` сохраняет текущий путь и query string. Не добавляйте языковой префикс вручную: базовый язык его не использует.
