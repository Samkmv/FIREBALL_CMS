# FIREBALL Admin UI 2.0 — Component Kit и интеграция плагинов

Admin UI 2.0 подключается только на административных маршрутах `/admin*`. Плагины не должны копировать `admin-ui.css`: используйте общий shell, Bootstrap-компоненты и классы `fb-*`.

## Обёртка страницы

```php
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('my_plugin_title'),
    'subtitle' => return_translation('my_plugin_subtitle'),
    'actions' => '<a class="btn btn-primary" href="' . base_href('/admin/my-plugin/create') . '">...</a>',
]) ?>

<div class="fb-card">
    <div class="fb-card-header">
        <h2 class="fb-card-title">...</h2>
    </div>
    <div class="fb-card-body">...</div>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
```

`shell_open` создаёт AdminLayout, page header и content container. `shell_close` завершает layout и добавляет mobile navigation, command palette и modal root. Старые параметры `container_class`, `sidebar_col_class` и `main_col_class` принимаются для обратной совместимости; grid-параметры больше не определяют chrome.

## Design tokens

Основные tokens находятся в `public/assets/default/css/admin-ui.css`:

- `--fb-color-primary`, `--fb-color-*-soft`;
- `--fb-color-background`, `--fb-color-surface`, `--fb-color-text`, `--fb-color-border`;
- `--fb-space-1` … `--fb-space-8`;
- `--fb-radius-sm` … `--fb-radius-xl`;
- `--fb-shadow-sm`, `--fb-shadow-md`, `--fb-shadow-lg`;
- `--fb-sidebar-width`, `--fb-topbar-height`;
- `--fb-transition-fast`, `--fb-transition-base`.

Тёмная тема задаёт собственные значения tokens в `[data-bs-theme="dark"]`. В plugin CSS используйте tokens вместо фиксированных цветов.

## Компоненты

- Card: `.fb-card`, `.fb-card-header`, `.fb-card-title`, `.fb-card-subtitle`, `.fb-card-body`, `.fb-card-footer`.
- StatCard: `.fb-stat-card`, `.fb-stat-icon`, `.fb-stat-value`, `.fb-stat-label`, `.fb-stat-meta`.
- Button: штатные `.btn`, `.btn-primary`, `.btn-outline-secondary`, `.btn-danger`; Admin UI автоматически унифицирует размеры, radius и состояния.
- Form: штатные `.form-label`, `.form-control`, `.form-select`, `.form-check`, `.invalid-feedback`, `get_validation_class()` и `get_errors()`.
- Table: используйте `admin/partials/table` либо `.table-responsive > .table`. Существующий `datatable.js` и responsive table helper продолжают работать.
- Badge/status: `.badge` или `.fb-badge`; всегда добавляйте текстовое значение статуса.
- Alert: сохраняйте `session()->setFlash('success|error|warning|info', $message)`. Не создавайте отдельный toast для результата серверной операции.
- Modal/dropdown/tabs: используйте штатную Bootstrap-разметку. Admin UI задаёт общую тему, mobile fullscreen и stacking.
- EmptyState: `.fb-empty-state` и `.fb-empty-state-icon`.
- Skeleton: `.fb-skeleton`; добавляйте корректное `aria-busy="true"` контейнеру.
- ActivityFeed: `.fb-activity-list`, `.fb-activity-item`, `.fb-activity-icon`, `.fb-activity-copy`.

## Пункт sidebar

```php
add_filter('admin_menu', static function (array $items): array {
    $items[] = [
        'group' => 'applications',
        'label' => return_translation('my_plugin_menu'),
        'href' => base_href('/admin/my-plugin'),
        'icon' => 'ci-box',
        'plugin_menu' => true,
        'order' => 50,
    ];

    return $items;
});
```

Поддерживаются `badge`, `badge_class`, `badge_title`, `creator_only` и `children`. Вложенный элемент использует те же поля. Sidebar автоматически добавляет active state, mobile drawer, collapsed tooltip и команды навигации.

## Быстрое действие Dashboard

```php
add_filter('admin_quick_actions', static function (array $actions, array $user): array {
    $actions[] = [
        'label' => return_translation('my_plugin_create'),
        'href' => base_href('/admin/my-plugin/create'),
        'icon' => 'ci-plus',
        'color' => 'var(--fb-color-purple)',
        'soft' => 'var(--fb-color-purple-soft)',
    ];

    return $actions;
}, 10);
```

Frontend-видимость не заменяет проверку права в route/controller.

## Команда command palette

```php
add_filter('admin_command_palette_commands', static function (array $commands, array $user): array {
    $commands[] = [
        'label' => return_translation('my_plugin_command'),
        'href' => base_href('/admin/my-plugin'),
        'category' => return_translation('my_plugin_menu'),
        'icon' => 'ci-box',
        'keywords' => 'optional search aliases',
        'creator_only' => false,
    ];

    return $commands;
}, 10);
```

Команды sidebar регистрируются автоматически. Палитра поддерживает `Ctrl/Cmd + K`, стрелки, Enter, Escape и focus trap.

## Dashboard widget и stat card

```php
add_filter('admin_dashboard_widgets', static function (array $widgets, array $context): array {
    $widgets[] = [
        'title' => return_translation('my_plugin_widget'),
        'span' => 1, // 1..3
        'content' => view()->renderPartial('my-plugin/widget', $context),
    ];

    return $widgets;
}, 10);

add_filter('admin_dashboard_stat_cards', static function (array $cards, array $stats, array $user): array {
    $cards[] = [
        'label' => return_translation('my_plugin_stat'),
        'value' => 0, // только реальное значение
        'icon' => 'ci-activity',
        'variant' => 'is-purple',
        'href' => base_href('/admin/my-plugin'),
        'meta' => return_translation('my_plugin_stat_hint'),
    ];

    return $cards;
}, 10);
```

`content` считается доверенным server-rendered HTML активного плагина. Пользовательские значения внутри него должны проходить через `htmlSC()`.

## Activity и уведомления

- Для Dashboard activity используйте фильтр `admin_dashboard_activity`.
- Для realtime notification center сохраняется фильтр `notification_feed_items`.
- Для результатов POST/redirect используйте session flash.

## JavaScript

Глобальная логика находится в `public/assets/default/js/admin-ui.js` и использует `data-fb-*`. После инициализации отправляется событие `fireball:admin:ready`; публичный API доступен как `window.FireballAdmin`.

Plugin JavaScript:

- не должен переопределять глобальные Bootstrap/Fireball объекты;
- должен инициализироваться внутри собственного root;
- должен поддерживать повторную инициализацию без дублирования handlers;
- должен реагировать на `fireball:admin:resize`, если визуал зависит от ширины sidebar;
- не должен хранить права только во frontend.

## Локализация и безопасность

Все plugin strings хранятся в `plugins/<slug>/lang/<locale>.php`. URL создаются через `base_href()`, вывод — через `htmlSC()`, формы сохраняют `get_csrf_field()`. Маршруты плагина обязаны использовать существующие `auth`/`admin` middleware и собственные серверные permission checks.
