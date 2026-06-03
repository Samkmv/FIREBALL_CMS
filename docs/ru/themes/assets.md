# Assets

Assets темы находятся в `assets/`.

```text
/assets
  /css
  /js
  /images
```

## CSS

Основной файл новой темы:

```text
assets/css/style.css
```

Подключение:

```php
<link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
```

## JavaScript

Основной файл новой темы:

```text
assets/js/theme.js
```

Подключение:

```php
<script src="<?= theme_asset('js/theme.js') ?>"></script>
```

## Изображения

Изображения кладите в `assets/images/`.

```php
<img src="<?= theme_asset('images/hero.jpg') ?>" alt="Hero">
```

## Безопасность assets

- Не храните секреты и конфиги.
- Не добавляйте `.env`, `.htaccess`, архивы и backup-файлы.
- Не подключайте assets через `../`.
- Не кладите PHP-файлы в assets.

## Экспорт

При экспорте CMS добавляет в ZIP только разрешённые файлы темы и исключает временные, системные и опасные файлы.
