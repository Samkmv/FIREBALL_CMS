# Theme API

Theme API — единственная точка доступа темы к данным CMS. Тема не выполняет SQL-запросы.

```php
site_name();
site_url('/posts');
theme_asset('css/style.css');
setting('site_description');
current_user();
current_locale();
available_locales();
switch_locale_url('en');
get_menu('header');
get_pages(['limit' => 10]);
get_posts(['limit' => 10]);
render_partial('sidebar', ['items' => $items]);
```

Те же операции доступны как методы `theme()` и через фасад `\FBL\Theme`.

Рекомендация: экранируйте текст через `htmlSC()`. HTML контента страницы и записи уже очищается CMS.

Типичная ошибка: обращаться к `db()` из шаблона. Это связывает тему со схемой БД и нарушает совместимость.
