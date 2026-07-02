# Плагины FIREBALL CMS

Плагины расширяют CMS без изменения файлов ядра. Через них можно добавлять commerce, car-rental, subscriptions, payments, CRM, галереи и другие модули.

## Структура

```text
plugins/
  toy-car-rental/
    plugin.json
    Plugin.php
    routes.php
    admin.php
    migrations/
    views/
    assets/
    lang/
```

## plugin.json

```json
{
  "name": "Toy Car Rental",
  "slug": "toy-car-rental",
  "version": "1.0.0",
  "description": "Модуль проката детских машинок с таймером, оплатой и статистикой.",
  "author": "FIREBALL CMS",
  "requires": {
    "cms": ">=1.7.0"
  }
}
```

Обязательные поля: `slug`, `name`, `version`. Slug может содержать только латиницу, цифры, дефис и подчёркивание. Имя папки должно совпадать со slug.

## Жизненный цикл

Класс плагина должен реализовать `FBL\Plugins\PluginInterface`:

```php
interface PluginInterface
{
    public function install(): void;
    public function uninstall(): void;
    public function activate(): void;
    public function deactivate(): void;
    public function boot(): void;
}
```

Если `plugin.json` не содержит поле `class`, имя класса строится из slug. Для `toy-car-rental` это `FireballPluginToyCarRental`.

## Хуки

```php
add_action('hook_name', function ($payload) {
    // ...
}, 10);

do_action('hook_name', $payload);

add_filter('admin_menu', function (array $menu): array {
    $menu[] = [
        'label' => 'Прокат машинок',
        'href' => base_href('/admin/toy-rental'),
        'icon' => 'activity'
    ];

    return $menu;
});

$menu = apply_filters('admin_menu', $menu);
```

Хуки поддерживают несколько обработчиков и приоритеты.

## События

```php
fireball_listen('order.created', function ($payload) {
    // обработка события
});

fireball_event('order.created', ['id' => 100]);
```

События нужны для будущих модулей оплат, подписок, заявок и заказов.

## Маршруты

Активный плагин может объявлять public routes в `routes.php` и admin routes в `admin.php`.

```php
$router->get('/toy-car-rental', function () {
    return plugin_view('toy-car-rental', 'frontend', [
        'title' => 'Toy Car Rental',
    ]);
});
```

Admin routes должны использовать middleware:

```php
$router->get('/admin/toy-rental', function () {
    return plugin_view('toy-car-rental', 'admin-dashboard');
})->middleware(['auth', 'admin']);
```

## Миграции

SQL-файлы из `migrations/*.sql` выполняются при установке плагина один раз. Выполненные миграции сохраняются в `plugin_migrations`.

## Настройки

```php
plugin_setting_set('toy-car-rental', 'default_duration', 10);
$duration = plugin_setting('toy-car-rental', 'default_duration', 10);
```

Настройки хранятся в `plugin_settings`.

## Языковые файлы

Переводы плагина хранятся внутри самого плагина:

```text
plugins/toy-car-rental/lang/ru.php
plugins/toy-car-rental/lang/en.php
```

Активные плагины подключают свои языковые файлы автоматически. Не добавляйте строки плагина в `app/Languages/*.php`.

## Безопасность

PluginManager проверяет slug, запрещает выход за пределы `/plugins`, не подключает символические ссылки и логирует ошибки плагинов. Повреждённый `plugin.json` не должен ронять CMS.

## Первый рабочий плагин

Плагин `plugins/toy-car-rental` добавляет модуль проката детских машинок: машинки, активные поездки, таймеры, историю, оплату, статистику и настройки.

## Будущие модули

Commerce, car-rental, subscriptions и payments должны подключаться через те же точки расширения: routes, hooks, events, migrations, settings и admin_menu. Ядро CMS при этом не изменяется.
