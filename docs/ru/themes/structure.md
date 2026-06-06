# Структура темы

Тема находится в `/themes/{slug}` и использует следующую стандартную структуру:

```text
/themes/theme-name
  theme.json
  preview.png
  /templates
    layout.php
    home.php
    page.php
    post.php
    category.php
    search.php
    archive.php
    404.php
  /partials
    header.php
    footer.php
    menu.php
    sidebar.php
  /assets
    /css
    /js
    /images
```

Для обратной совместимости старой теме достаточно прежнего базового набора. Если новый шаблон отсутствует, CMS загрузит его из дефолтной темы, сохранив `layout.php` активной темы.

## Обязательный базовый набор

Для установки темы обязательны `theme.json`, шаблоны `layout.php`, `home.php`,
`page.php`, `post.php` и partials `header.php`, `footer.php`, `menu.php`.
Новые шаблоны и `sidebar.php` входят в стандарт генератора, но не делают старые
темы невалидными.

## Ограничения

PHP-шаблоны должны находиться только в `templates/` и `partials/`. Не добавляйте
в тему `.env`, `.htaccess`, `.git`, `node_modules`, `vendor`, backup-файлы и
исполняемые файлы вроде `.sh`, `.exe` или `.phar`.
