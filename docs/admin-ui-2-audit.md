# FIREBALL Admin UI 2.0 — технический аудит

Дата аудита: 30 июля 2026.

## Текущая архитектура

- Общий HTML layout находится в `app/Views/layouts/default.php`. Он подключает публичные header/footer, flash alert, глобальные стили и JavaScript, а затем выводит содержимое представления.
- Обёртка всех штатных административных страниц находится в `app/Views/themes/default/admin/shell_open.php` и `shell_close.php`. Её также используют активные плагины `toy-car-rental` и `vpn-manager-v2`, поэтому этот слой является основной точкой безопасной интеграции Admin UI 2.0.
- Боковое меню собирается в `app/Views/themes/default/admin/nav.php`, а пользовательский блок и быстрые ссылки — в `app/Views/themes/default/admin/sidebar.php`.
- Отдельной административной верхней панели сейчас нет: на admin-маршрутах показывается публичный header из `app/Views/layouts/default.php`.
- Dashboard формируется контроллером `App\Controllers\AdminController::dashboard()` и представлением `app/Views/themes/default/admin/dashboard.php`.

## Маршруты, авторизация и права

- Системные маршруты определены в `config/routes.php`; URL административных страниц имеют префикс `/admin`.
- Все admin-маршруты защищены middleware `auth` и `admin`; опасные операции дополнительно защищены middleware `creator`.
- Серверная проверка административной роли выполняется в `core/Auth.php` и `core/Middleware/Admin.php`.
- Текущее меню скрывает элементы с `creator_only`. UI 2.0 должен сохранить это поведение и не использовать скрытие frontend-элемента как проверку права.
- CSRF уже реализован через meta-тег и `get_csrf_field()`. Формы и существующие POST-маршруты менять не требуется.

## Глобальные стили и JavaScript

- Базовая UI-библиотека: локальный Bootstrap/Cartzilla из `public/assets/default/css/theme.min.css` и `public/assets/default/js/theme.min.js`.
- Глобальные пользовательские стили: `public/assets/default/css/style.css`.
- Глобальная логика: `public/assets/default/js/main.js`; отдельно подключаются `theme-switcher.js`, `select-init.js`, `datatable.js`, `admin-delete-modal.js` и специализированные page scripts.
- Локальные иконки: Cartzilla Icons (`public/assets/default/icons/`); локальные шрифты Inter и Roboto уже присутствуют.
- Графики уже работают через локальные Chart.js/ApexCharts и `admin-analytics.js`; новую внешнюю зависимость добавлять не нужно.

## Меню и интеграция плагинов

- Системные группы и пункты меню задаются в `admin/nav.php`.
- Расширение выполняется через фильтры `admin_menu_groups`, `admin_menu` и реестр `FBL\Menu::register()`.
- Активные плагины загружаются `core/Plugins/PluginManager.php`, регистрируют маршруты и пункты меню в `Plugin::boot()`.
- PluginManager рендерит plugin views внутри того же `app/Views/layouts/default.php`; плагины уже используют `admin/shell_open`/`shell_close`, поэтому новый shell автоматически распространяет дизайн на их страницы.
- Плагинные переводы регистрируются отдельно менеджером плагинов; переносить их в ядро не требуется.

## Уведомления и локализация

- Серверные результаты операций используют session flash и `get_alerts()` из `helpers/helpers.php`; сами alert partials находятся в `app/Views/themes/default/incs/alert_*.php`.
- Realtime-лента агрегируется `App\Models\NotificationCenter`, включая chat, support, system notifications, updates и фильтр `notification_feed_items` для плагинов.
- Переводы ядра хранятся в `app/Languages/ru.php`, `en.php`, `de.php`, `zh-cn.php`; доступ выполняется через `return_translation()`/`print_translation()`.

## Компоненты для переиспользования

- Bootstrap dropdown, offcanvas, modal и form controls.
- Существующие admin partials для таблиц и адаптивных карточек в `app/Views/themes/default/admin/partials/`.
- Локальные Cartzilla Icons, Chart.js/ApexCharts, Choices.js и Simplebar.
- Существующие route helpers `base_href()`/`base_url()`, flash alerts, CSRF, фильтры и plugin view renderer.
- Реальные Dashboard-данные из `Admin`, `Analytics`, `Support`, `NotificationCenter`, `UpdateCenter`, `SecurityLog` и plugin filters.

## Необходимый рефакторинг

- Отделить административный chrome от публичного header/footer по фактическому `/admin` path, а не по роли пользователя.
- Превратить `shell_open`/`shell_close` в единый AdminLayout с sidebar, topbar, content, mobile drawer, bottom navigation и palette roots.
- Вынести Admin UI 2.0 tokens/compatibility styles из общего `style.css` в отдельный локальный stylesheet и подключать его только на admin-маршрутах.
- Вынести интерактивную логику shell/palette/theme/sidebar в отдельный admin script с data-атрибутами.
- Переработать Dashboard на существующих данных и расширить его безопасными фильтрами для плагинных карточек, быстрых действий и activity items.

## Риски и меры

- Публичный layout общий для всех страниц: ветвление должно использовать нормализованный URL `/admin`, иначе публичные страницы администратора потеряют обычный header/footer.
- Plugin views зависят от `shell_open`/`shell_close`: сигнатуры передаваемых `title`, `subtitle`, `actions` и grid-related параметров необходимо сохранить.
- Часть старых страниц использует Bootstrap utility-классы и inline styles: compatibility layer должен улучшать их без массовой миграции и без изменения бизнес-логики.
- Flash alert сейчас выводится layout-ом до содержимого: на admin-маршрутах его нужно перенести внутрь admin content, не меняя session API.
- Текущий theme switcher сохраняет `light`/`dark`/`auto`; новый UI должен сохранить ключ storage и раннюю инициализацию, чтобы избежать мигания темы.
- Автоматическая проверка обновлений может обращаться к внешнему источнику; Dashboard не должен добавлять новые тяжёлые проверки.

## Граница текущей итерации

В текущей итерации реализуются design tokens, темы, AdminLayout, sidebar/topbar, mobile UI, Dashboard, базовый Component Kit, compatibility layer, flash/modal/dropdown/table/form states, command palette и документация. Настраиваемая раскладка виджетов и Workspace остаются архитектурными точками расширения, как допускает ТЗ.
