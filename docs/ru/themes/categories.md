# Категории

Маршрут категории: `/category/{slug}`. Шаблон: `templates/category.php`.

Доступны `$category`, `$posts`, `$pagination`.

```php
<h1><?= htmlSC($category['name']) ?></h1>
<p><?= htmlSC($category['description']) ?></p>

<?php foreach ($posts as $post): ?>
    <a href="<?= base_href('/posts/' . $post['slug']) ?>">
        <?= htmlSC($post['title']) ?>
    </a>
<?php endforeach; ?>
```

Категория содержит `id`, `name`, `slug`, `description`, `url` и SEO-поля. Не формируйте URL категории вручную, если доступно `$category['url']`.
