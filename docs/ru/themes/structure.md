# Структура темы

Минимальная тема находится в `/themes/{slug}`.

```text
/themes/my_theme
  theme.json
  preview.png
  /templates
    layout.php
    home.php
    page.php
    post.php
  /partials
    header.php
    footer.php
    menu.php
  /assets
    /css
      style.css
    /js
      theme.js
    /images
```

## Обязательные файлы

- `theme.json` — описание темы.
- `templates/layout.php` — общий layout.
- `templates/home.php` — главная страница.
- `templates/page.php` — обычная CMS-страница.
- `templates/post.php` — запись или пост.
- `partials/header.php` — шапка.
- `partials/footer.php` — подвал.
- `partials/menu.php` — пример меню.

## Обязательные директории

- `templates/`
- `partials/`
- `assets/`
- `assets/css/`
- `assets/js/`
- `assets/images/`

## Что можно добавлять

Можно добавлять дополнительные CSS, JS, изображения и PHP partials внутри разрешённых папок. PHP-шаблоны должны оставаться только в `templates/` и `partials/`.

## Что нельзя добавлять

- `.env`
- `.htaccess`
- `.git/`
- `node_modules/`
- `vendor/`
- backup-файлы вроде `file.php.bak`
- исполняемые файлы вроде `.sh`, `.exe`, `.phar`
