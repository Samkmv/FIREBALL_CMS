# Архивы

Маршрут `/archive` использует `templates/archive.php`.

Доступны `$posts` и `$pagination`.

```php
<?php foreach ($posts as $post): ?>
    <time datetime="<?= htmlSC($post['published_at']) ?>">
        <?= date('d.m.Y', strtotime($post['published_at'])) ?>
    </time>
    <a href="<?= base_href('/posts/' . $post['slug']) ?>"><?= htmlSC($post['title']) ?></a>
<?php endforeach; ?>
```

При отсутствии шаблона CMS использует `archive.php` дефолтной темы.
