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


## Разрешение файлов и диагностика

```php
\FBL\Theme::render('auth/settings', $data);
\FBL\Theme::partial('password_field', $data);
\FBL\Theme::resolveFile('templates', 'auth/login');
\FBL\Theme::resolveFile('partials', 'auth/profile_security', 'my_theme');
\FBL\Theme::publicTemplates();
\FBL\Theme::diagnostics('my_theme'); // Только check_admin().
theme_asset_versioned('js/chat.js');
```

`resolveFile()` возвращает `path`, `source` (slug темы), `fallback` или `null`.
Рендеринг и диагностика используют один resolver. `diagnostics()` возвращает
`active_theme`, `selected_theme`, `valid`, `required` и `coverage`.
В `coverage` для каждого файла есть `present`, `source`, `missing`.
Встроенные публичные представления установленных плагинов также включены;
источник `plugin:{slug}` означает fallback на оригинальный файл плагина.
Для произвольных плагинов используйте `resolvePluginFile($slug, $view, $file, $themeSlug)`:
это тот же resolver, что использует `renderPlugin()`.

`renderPlugin($slug, $view, $data, $file)` подключает override
`plugins/{slug}/{view}` или оригинальное представление и тематический layout.
Для обычных плагинов предпочтителен совместимый helper `plugin_view()`.
Публичные partials таблиц подписок находятся в `partials/plugins/subscriptions/`.

Отсутствующий шаблон/layout вызывает исключение, отсутствующий partial возвращает
пустую строку. Ошибки PHP очищают буферы вывода. При `abort(..., 404)` сбой темы
переводит вывод на системную страницу, затем на минимальный HTML резерв;
HTTP-код остаётся 404, внутренний текст исключения не выводится.
Fallback применяется к отсутствующим файлам, а не к произвольным ошибкам PHP.

Assets разрешаются независимо; versioned URL использует manifest для фактически
разрешённого файла. Неизвестный безопасный asset сохраняет прежний URL-контракт,
но физический `assetPath()` возвращает пустую строку, если файла нет.
Traversal и symlink за пределы темы отклоняются; недопустимым путям URL не выдаётся.
