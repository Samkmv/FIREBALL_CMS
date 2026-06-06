# Поиск

Шаблон `templates/search.php` получает `$query`, `$results`, `$total`, `$pagination`.

```php
<?php foreach ($results as $result): ?>
    <article>
        <small><?= htmlSC($result['type']) ?></small>
        <a href="<?= htmlSC($result['url']) ?>"><?= htmlSC($result['title']) ?></a>
        <p><?= htmlSC($result['excerpt']) ?></p>
    </article>
<?php endforeach; ?>
```

Стандартные типы результата: `page` и `post`. Не выполняйте повторный поиск из шаблона: CMS уже подготовила данные.
