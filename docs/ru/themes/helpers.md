# Helper-функции

В шаблонах темы доступны общие helper-функции CMS.

## theme_asset()

Возвращает URL к assets активной или preview-темы.

```php
<?= theme_asset('css/style.css') ?>
```

Результат:

```text
/themes/default/assets/css/style.css
```

## base_href()

Возвращает ссылку с учётом языка сайта.

```php
<a href="<?= base_href('/posts') ?>">Записи</a>
```

## base_url()

Возвращает абсолютный URL от базового адреса проекта.

## site_setting()

Получает настройку сайта.

```php
<?= htmlSC(site_setting('site_title', SITE_NAME)) ?>
```

## htmlSC()

Экранирует строку для безопасного вывода в HTML.

```php
<?= htmlSC($page['title']) ?>
```

## get_csrf_meta() и get_csrf_field()

Используются для форм и AJAX-запросов.

```php
<?= get_csrf_field() ?>
```

## $this->partial()

Подключает partial текущей темы.

```php
<?= $this->partial('footer', get_defined_vars()) ?>
```

## $this->content

В layout содержит HTML текущего шаблона страницы.

## Стандартизированный API

Для новых тем используйте `site_name()`, `site_url()`, `setting()`,
`current_user()`, `current_locale()`, `available_locales()`,
`switch_locale_url()`, `get_menu()`, `get_pages()`, `get_posts()` и
`render_partial()`. Полное описание и примеры находятся в главе **Theme API**.

## getLegalInformationMenu()

Theme API возвращает опубликованные страницы, отмеченные флагом
`show_in_legal_information`. Результат уже отсортирован и кешируется CMS.

```php
<?php $legalMenu = theme()->getLegalInformationMenu(); ?>

<?php foreach ($legalMenu as $item): ?>
    <a href="<?= htmlSC($item['href']) ?>"><?= htmlSC($item['label']) ?></a>
<?php endforeach; ?>
```

Также доступен статический фасад:

```php
<?php $legalMenu = \FBL\Theme::getLegalInformationMenu(); ?>
```

Элемент меню содержит `id`, `href`, `label` и `menu_order`.

Для перевода подписи добавьте в языковой файл ключ
`page_menu_<slug>`, заменив дефисы slug на подчёркивания. Например, для
`privacy-policy` используется `page_menu_privacy_policy`. Если ключ отсутствует,
CMS выводит `menu_title` или `title` страницы на языке по умолчанию.
