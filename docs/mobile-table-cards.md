# Единый шаблон мобильных таблиц

В административной панели используется один мобильный компонент таблиц:

```php
view()->renderPartial('admin/partials/responsive_table_cards', ['cards' => $cards])
```

Обычно компонент не подключается напрямую. Новые таблицы должны идти через общий partial:

```php
<?= view()->renderPartial('admin/partials/table', [
    'columns' => $columns,
    'rows' => $rows,
    'mobile_cards' => $mobileCards,
]) ?>
```

На desktop остается обычная Bootstrap/Cartzilla table. Если передан `mobile_cards`, таблица скрывается только на мобильной ширине, а вместо нее выводятся карточки Cartzilla `card` + `card-body` + `list-group` + `badge` + `dropdown`.

## Данные карточки

Минимальный формат:

```php
$mobileCards[] = [
    'id' => (int)$item['id'],
    'title' => (string)$item['title'],
    'slug' => (string)$item['slug'],
    'category' => (string)$item['category_name'],
    'author' => (string)$item['author_name'],
    'order' => (int)$item['sort_order'],
    'views' => (int)$item['views_count'],
    'status' => [
        ['label' => return_translation('admin_posts_status_published'), 'class' => 'text-success bg-success-subtle'],
    ],
    'published_at' => date('d.m.Y H:i', strtotime($item['published_at'])),
    'actions' => $actionsHtml,
];
```

Поля можно не передавать. Компонент автоматически скрывает строки без значения.

## Новое поле

Дополнительные строки передаются через `extra_fields`:

```php
'extra_fields' => [
    [
        'label' => return_translation('admin_pages_col_menu_title'),
        'value' => (string)$page['menu_label'],
    ],
    [
        'label' => return_translation('admin_users_col_role'),
        'html' => '<span class="badge fs-xs rounded-pill text-secondary bg-secondary-subtle">User</span>',
    ],
],
```

Если поле содержит готовый HTML, используйте ключ `html` и заранее экранируйте пользовательские значения через `htmlSC()`.

## Действия

Для действий используется Bootstrap/Cartzilla Dropdown. В существующих таблицах удобнее собрать dropdown один раз и передать тот же HTML в desktop-таблицу и мобильную карточку:

```php
$actionsHtml = $renderActions($item);

$mobileCards[] = [
    'id' => (int)$item['id'],
    'title' => (string)$item['title'],
    'actions' => $actionsHtml,
];
```

Dropdown должен содержать `data-bs-toggle="dropdown"` и `data-bs-display="static"`. Для мобильных карточек родительские блоки имеют `overflow: visible`, а меню получает повышенный `z-index`, чтобы оно не обрезалось карточкой.

## Новые модули и плагины

Новые административные таблицы CMS и плагинов должны использовать `admin/partials/table`.

Если таблица передает `columns` и `rows`, общий partial автоматически построит базовые мобильные карточки. Для точного порядка строк, бейджей, дат, изображений и dropdown-действий передавайте `mobile_cards`.

Не создавайте отдельные мобильные шаблоны для сущностей. При изменении дизайна `admin/partials/responsive_table_cards.php` изменения должны применяться ко всем таблицам, которые используют общий partial.

## Применение в первом этапе

На первом этапе компонент подключен к записям, страницам, категориям, пользователям и ролям. Отдельного admin-раздела меню в текущей CMS нет: меню страниц хранится в таблице страниц через `menu_title`, `menu_order`, `show_in_header` и `show_in_footer`, поэтому мобильный вид меню покрывается карточками страниц и их `extra_fields`.
