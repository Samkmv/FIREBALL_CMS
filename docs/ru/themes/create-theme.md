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
templates/posts.php
templates/category.php
templates/search.php
templates/archive.php
templates/404.php
partials/header.php
partials/footer.php
partials/menu.php
partials/sidebar.php
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

## Совместимость и полное публичное покрытие

Минимум для валидности существующих тем не изменён: `theme.json`,
`templates/layout.php`, `home.php`, `page.php`, `post.php` и
`partials/header.php`, `footer.php`, `menu.php`. Обязательные каталоги:
`templates`, `partials`, `assets`, `assets/css`, `assets/js`, `assets/images`.
Новые файлы не добавлены в обязательный минимум.

Генератор теперь создаёт содержательный `templates/posts.php`: список записей,
экранированные заголовки, ссылки, описания, пустое состояние и пагинацию.

Полный рекомендуемый набор публичных шаблонов:

```text
templates/layout.php
templates/home.php
templates/page.php
templates/posts.php
templates/post.php
templates/category.php
templates/archive.php
templates/search.php
templates/contacts.php
templates/support.php
templates/support_article.php
templates/auth/login.php
templates/auth/two_factor_challenge.php
templates/auth/two_factor_recovery.php
templates/auth/reset_password.php
templates/auth/register.php
templates/auth/profile.php
templates/auth/settings.php
templates/chat/index.php
templates/chat/group.php
templates/offline.php
templates/404.php
```

Отсутствующие шаблоны, layout, partials и assets независимо разрешаются из
`default`. Поэтому старые темы остаются валидными и получают новые страницы.
Формы восстановления пароля находятся в `auth/login.php`; отдельного HTML GET
маршрута `/forgot-password` нет.

Общие partials: `header`, `footer`, `menu`, `menu-links`, `mini-cart`, `sidebar`,
`password_field`, `auth/profile`, `auth/profile_overview`, `auth/profile_information`,
`auth/profile_security`, `auth/profile_notifications`, `chat/viewport`.
Не подключайте соседние файлы напрямую через `__DIR__`, если для них нужен
независимый fallback: используйте `$this->partial($name, $data)`.

Публичные страницы плагинов переопределяются по пути
`templates/plugins/{pluginSlug}/{view}.php`, например
`templates/plugins/subscriptions/public/plans.php`.
Порядок: выбранная тема → default → оригинальное представление плагина.
Layout разрешается через Theme API. `plugin_view()` сохраняет аргументы;
на маршрутах `/admin` и при `admin_context=true` он сохраняет legacy layout,
а при `layout=false` возвращает фрагмент без layout.

Диагностика в существующем списке тем показывает обязательный минимум,
публичное покрытие и источник каждого файла. Она доступна администраторам.
Cartzilla остаётся библиотекой публичных компонентов; шаблон админки и редактор
тем не заменяются.
