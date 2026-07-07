# Шаблоны

Шаблоны находятся в `templates/` и отвечают за основные страницы сайта.

## layout.php

`layout.php` — общий каркас документа. Обычно он подключает header, menu, основной контент, footer, CSS и JS.

```php
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlSC($title ?? site_setting('site_title', SITE_NAME)) ?></title>
    <link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
</head>
<body>
    <?= $this->partial('header', get_defined_vars()) ?>
    <?= $this->partial('menu', get_defined_vars()) ?>
    <?= $this->content ?>
    <?= $this->partial('footer', get_defined_vars()) ?>
    <script src="<?= theme_asset('js/theme.js') ?>"></script>
</body>
</html>
```

## home.php

Доступны `$page`, `$posts`, `$settings`, `$user`, `$locale`.

## page.php

Доступны `$page`, `$settings`, `$user`, `$locale`.

```php
<h1><?= htmlSC($page['title'] ?? '') ?></h1>
<div><?= $page['content'] ?? '' ?></div>
```

## post.php

Доступны `$post`, `$author`, `$category`, `$settings`, `$user`.

```php
<h1><?= htmlSC($post['title'] ?? '') ?></h1>
<div><?= $post['content'] ?? '' ?></div>
```

## category.php

Доступны `$category`, `$posts`, `$pagination`.

## search.php

Доступны `$query`, `$results`, `$total`, `$pagination`.

## archive.php

Доступны `$posts`, `$pagination`.

## 404.php

Доступна `$settings`. Ошибка 404 рендерится через общий `layout.php`.

## Fallback

Если в старой теме отсутствует `category.php`, `search.php`, `archive.php` или
`404.php`, CMS использует соответствующий шаблон дефолтной темы. При этом
сохраняется `layout.php` активной темы.

## Безопасность в шаблонах

- Экранируйте пользовательский текст через `htmlSC()`.
- Не подключайте файлы через данные из запроса.
- Не используйте `../` для assets.
- Не выполняйте SQL-запросы в шаблонах.
