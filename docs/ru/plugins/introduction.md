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

## Независимые обновления

Установленные плагины могут проверяться и обновляться отдельно на странице «Плагины». Операция заменяет каталог только выбранного плагина: ядро CMS и каталоги остальных плагинов не изменяются.

Источник обновлений задаётся в `plugin.json` самого плагина:

```json
{
  "slug": "toy-car-rental",
  "version": "1.1.0",
  "release_notes": [
    "Added a booking calendar.",
    "Fixed extension price calculation."
  ],
  "release_notes_i18n": {
    "ru": [
      "Добавлен календарь бронирований.",
      "Исправлен расчёт стоимости продления."
    ],
    "en": [
      "Added a booking calendar.",
      "Fixed extension price calculation."
    ],
    "de": [
      "Ein Buchungskalender wurde hinzugefügt.",
      "Die Preisberechnung für Verlängerungen wurde korrigiert."
    ],
    "zh-cn": [
      "新增预订日历。",
      "修复续租价格计算。"
    ]
  },
  "update": {
    "enabled": true,
    "provider": "github_directory",
    "repository": "owner/repository",
    "branch": "main",
    "path": "plugins/toy-car-rental"
  }
}
```

Для выпуска обновления увеличьте `version` конкретного плагина и опубликуйте его каталог вместе с `plugin.json` в настроенной ветке. Поле `release_notes` необязательно и остаётся совместимым со старыми версиями CMS: оно принимает строку с переносами или массив до 10 коротких fallback-пунктов. Переводы задаются в `release_notes_i18n` объектом с ключами локалей `ru`, `en`, `de`, `zh-cn`; значение каждой локали также может быть строкой или массивом. Новая CMS поддерживает и локализованный объект непосредственно в `release_notes`, но отдельное поле `release_notes_i18n` рекомендуется для совместимости старого updater с английским fallback. Админка выбирает перевод по текущему языку и fallback-цепочке перед установкой. CMS сначала фиксирует точный commit, повторно проверяет manifest из этого commit и устанавливает из того же commit только путь `update.path`. Поэтому изменения другого плагина в той же ветке не устанавливаются вместе с выбранным.

На странице плагинов можно проверить один источник либо все настроенные источники одной кнопкой. Результаты старше шести часов автоматически перепроверяются при открытии страницы; запросы проверки отправляются в GitHub без использования промежуточного HTTP-кеша. Сбой одного репозитория не прерывает общую проверку. Установка по-прежнему запускается отдельно для каждого плагина, чтобы ошибка одного пакета не затронула остальные. Если опубликованная версия меньше установленной, CMS показывает предупреждение об устаревшем источнике и не предлагает автоматический откат. Проверка и установка доступны административным ролям `admin` и `creator` и защищены CSRF. Для приватного GitHub-репозитория используется token из штатных настроек центра обновлений; token не сохраняется в manifest плагина и не выводится в журнал.

Перед заменой создаётся ZIP-копия текущего каталога в `storage/plugin-updates/backups/{slug}`. Хранятся три последние копии каждого плагина. После атомарной замены выполняются только новые миграции выбранного плагина, его статус `active`/`inactive` сохраняется. При ошибке до завершения обновления файлы автоматически возвращаются из скрытой rollback-копии. Миграции обновления должны быть обратимо совместимыми со старой версией: уже выполненный SQL нельзя универсально отменить файловым откатом.

Обновления ядра из ZIP не включают каталог `plugins`. Одновременный запуск обновления ядра и плагина блокируется общим lock-файлом. При эксплуатации CMS непосредственно как Git working copy обновлённые через панель файлы плагина будут считаться локальными изменениями Git; перед Git-обновлением ядра их состояние нужно согласовать вручную.

## Настройки

```php
plugin_setting_set('toy-car-rental', 'default_duration', 10);
$duration = plugin_setting('toy-car-rental', 'default_duration', 10);
```

Настройки хранятся в `plugin_settings`.

## Уведомления

Плагины создают уведомления только через единый сервис CMS. Сервис сохранит уведомление внутри сайта и сам отправит Web Push, если пользователь включил push и у него есть активная подписка браузера или PWA.

```php
use App\Services\NotificationService;

NotificationService::create([
    'user_id' => $userId,
    'title' => 'Новая заявка',
    'message' => 'Поступила новая заявка от клиента',
    'type' => 'support_ticket',
    'action_url' => '/admin/support/tickets/123',
    'source' => 'support',
    'priority' => 'normal',
    'metadata' => [
        'ticket_id' => 123,
    ],
]);
```

Также доступен helper:

```php
notification_create([
    'user_id' => $userId,
    'title' => 'Поездка завершена',
    'message' => 'Прокат машинки завершён.',
    'type' => 'toy_rental',
    'action_url' => '/admin/toy-rental/rides',
    'source' => 'toy-car-rental',
]);
```

Не отправляйте Web Push напрямую из плагина. Если push отключён или подписка недействительна, уведомление всё равно останется в центре уведомлений сайта.

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
