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
