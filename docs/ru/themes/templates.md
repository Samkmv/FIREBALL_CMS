# Шаблоны

Шаблоны находятся в `templates/` и отвечают за основные страницы сайта.

## layout.php

`layout.php` — общий каркас документа. Обычно он подключает header, menu, основной контент, footer, CSS и JS.

```php
<!doctype html>
<html lang="<?= htmlSC(app()->get('lang')['code'] ?? 'en') ?>">
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

Используется для главной страницы. Доступные данные зависят от контроллера, например `title` и списки записей.

## page.php

Используется для обычных CMS-страниц. Обычно доступна переменная `$page`.

```php
<h1><?= htmlSC($page['title'] ?? '') ?></h1>
<div><?= $page['content'] ?? '' ?></div>
```

## post.php

Используется для записи. Обычно доступна переменная `$post`.

```php
<h1><?= htmlSC($post['title'] ?? '') ?></h1>
<div><?= $post['content'] ?? '' ?></div>
```

## Безопасность в шаблонах

- Экранируйте пользовательский текст через `htmlSC()`.
- Не подключайте файлы через данные из запроса.
- Не используйте `../` для assets.
- Не выполняйте SQL-запросы в шаблонах.
