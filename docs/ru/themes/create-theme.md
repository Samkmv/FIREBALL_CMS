# Создание темы из админки

Новая тема создаётся в админке:

```text
Админка → Внешний вид → Создать тему
```

## Поля формы

- Название темы
- Slug
- Автор
- Описание
- Версия
- Preview-файл

## Что создаёт CMS

После отправки CMS создаёт папку `/themes/{slug}` и базовую структуру:

```text
theme.json
templates/layout.php
templates/home.php
templates/page.php
templates/post.php
partials/header.php
partials/footer.php
partials/menu.php
assets/css/style.css
assets/js/theme.js
assets/images/
preview.png
```

## После создания

CMS показывает сообщение “Тема успешно создана” и предлагает:

- перейти к списку тем;
- активировать тему;
- открыть файлы темы.

## Предпросмотр

Перед активацией используйте предпросмотр. Он не меняет активную тему в настройках.

```text
/admin/themes/preview/my_theme
```

## Частые ошибки

- Папка с таким slug уже есть.
- Slug содержит пробелы или заглавные буквы.
- Нет прав на запись в `/themes`.
- `theme.json` был изменён вручную и стал невалидным JSON.
