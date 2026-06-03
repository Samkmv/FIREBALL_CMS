# Partials

Partials — это повторно используемые куски интерфейса. Они находятся в `partials/`.

## Обязательные partials

- `header.php`
- `footer.php`
- `menu.php`

## Подключение partial

Внутри layout или шаблона используйте:

```php
<?= $this->partial('header', get_defined_vars()) ?>
```

Первый аргумент — имя файла без `.php`. Второй аргумент — данные, которые будут доступны внутри partial.

## Пример header.php

```php
<header class="theme-header">
    <a href="<?= base_href('/') ?>">
        <?= htmlSC(site_setting('site_title', SITE_NAME)) ?>
    </a>
</header>
```

## Пример menu.php

```php
<nav>
    <a href="<?= base_href('/') ?>">Главная</a>
    <a href="<?= base_href('/posts') ?>">Записи</a>
    <a href="<?= base_href('/contacts') ?>">Контакты</a>
</nav>
```

## Рекомендации

- Держите partials маленькими.
- Не смешивайте бизнес-логику и HTML.
- Для сложных блоков создавайте отдельные partials, например `partials/card.php`.
