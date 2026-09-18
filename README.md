<div align="center">

# 🔥 FIREBALL CMS

**Расширяемая PHP CMS с собственной системой тем, плагинов, PWA, Web Push, медиаплеером FirePlayer и встроенным центром обновлений.**

[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Latest Release](https://img.shields.io/github/v/release/Samkmv/FIREBALL_CMS?display_name=tag)](https://github.com/Samkmv/FIREBALL_CMS/releases)
[![License](https://img.shields.io/github/license/Samkmv/FIREBALL_CMS)](LICENSE)
[![GitHub last commit](https://img.shields.io/github/last-commit/Samkmv/FIREBALL_CMS)](https://github.com/Samkmv/FIREBALL_CMS/commits/main)

[Релизы](https://github.com/Samkmv/FIREBALL_CMS/releases) ·
[Документация](docs/) ·
[FirePlayer](docs/fireplayer.md) ·
[Editor 2.0](docs/editor-2.md)

</div>

---

## О проекте

**FIREBALL CMS** — самостоятельная система управления контентом на PHP, рассчитанная не только на классические сайты и блоги, но и на расширяемые веб-проекты с собственной бизнес-логикой.

CMS объединяет:

- управление страницами, записями и категориями;
- адаптивную административную панель;
- собственную систему тем и **Theme API**;
- расширяемую архитектуру плагинов;
- встроенный редактор контента;
- PWA и Web Push;
- централизованную систему уведомлений;
- аналитику и GeoIP;
- чат;
- файловый менеджер;
- собственный медиаплеер **FirePlayer**;
- центр обновлений CMS и независимые обновления плагинов;
- мультиязычный интерфейс.

Архитектура разделяет ядро, темы и плагины, позволяя развивать функциональность без прямого изменения системных компонентов.

> **Текущая версия ветки `main`: `1.8.0-beta.15`**  
> FIREBALL CMS активно развивается. Для production-сайтов перед обновлением рекомендуется создавать резервную копию базы данных и файлов.

---

## ✨ Основные возможности

### 📝 Контент

FIREBALL CMS предоставляет базовые инструменты для построения информационных и корпоративных сайтов:

- записи;
- страницы;
- категории;
- публикация и снятие с публикации;
- SEO-friendly URL и slug;
- поиск и подсказки;
- просмотры и метаданные;
- FAQ и база знаний;
- формы обратной связи и заявки;
- файловые и медиа-вложения;
- встроенный чат;
- уведомления пользователей.

В проекте развивается собственный блочный редактор и **Editor 2.0** с отдельными модулями импорта, истории, registry и sanitization.

Подробнее: [`docs/editor-2.md`](docs/editor-2.md)

---

## 🎛 Административная панель

Админ-панель построена как полноценное рабочее пространство CMS и адаптирована под desktop и мобильные устройства.

Поддерживаются:

- обзор состояния сайта;
- статистические карточки;
- управление контентом;
- пользователи и роли;
- файловый менеджер;
- темы;
- плагины;
- аналитика;
- уведомления;
- PWA;
- настройки почты;
- настройки приватности;
- журнал безопасности;
- обслуживание базы данных;
- центр обновлений;
- адаптивные таблицы и мобильные карточки;
- светлая и тёмная темы интерфейса.

Для таблиц CMS и плагинов используется общий адаптивный шаблон.

Подробнее: [`docs/mobile-table-cards.md`](docs/mobile-table-cards.md)

---

## 🎨 Темы оформления

FIREBALL CMS имеет собственную систему тем.

Темы находятся в:

```text
themes/{theme-slug}/
```

Базовая структура:

```text
themes/example/
├── theme.json
├── templates/
├── partials/
└── assets/
```

Система тем поддерживает:

- переключение активной темы;
- `Theme API`;
- шаблоны главной страницы;
- страницы и записи;
- категории;
- архивы;
- поиск;
- страницу 404;
- partial-шаблоны;
- импорт и экспорт;
- копирование тем;
- предпросмотр;
- встроенный редактор файлов;
- проверку PHP перед сохранением;
- проверку `theme.json`;
- резервные копии изменяемых файлов;
- восстановление предыдущих версий.

В комплект входит системная тема:

```text
themes/default
```

Пользовательские темы должны взаимодействовать с CMS через Theme API и не изменять файлы ядра.

Документация находится в [`docs/`](docs/).

---

## 🧩 Плагины

Функциональность FIREBALL CMS может расширяться независимо от ядра.

Плагины находятся в:

```text
plugins/{plugin-slug}/
```

Типовая структура:

```text
plugins/example-plugin/
├── plugin.json
├── Plugin.php
├── routes.php
├── migrations/
├── views/
├── assets/
└── lang/
```

Plugin API поддерживает:

- install;
- activate;
- deactivate;
- uninstall;
- boot;
- маршруты;
- hooks и filters;
- события;
- миграции;
- административное меню;
- настройки;
- собственные assets;
- собственные views;
- локализацию внутри плагина;
- Notification API;
- независимые обновления.

Плагин может обновляться отдельно от CMS через `PluginUpdateService`.

### Плагины в текущем репозитории

| Плагин | Версия | Назначение |
|---|---:|---|
| **Calendar** | `1.0.2` | Личные и общие события, повторения, внутренние напоминания и PWA Push |
| **Camera Manager** | `1.3.4` | Управление объектами, регистраторами и RTSP/HLS-камерами |
| **Subscriptions** | `1.3.7` | Платные подписки, Robokassa, рекуррентные платежи и защита контента |
| **Toy Car Rental** | `1.0.1` | Прокат детских машинок, таймеры, тарификация, история и статистика |
| **VPN Management** | `0.26.0` | Управление подписками и конфигурациями 3x-ui |

Плагины не должны напрямую изменять ядро FIREBALL CMS.

---

## 🎬 FirePlayer

**FirePlayer 1.0.3** — собственный медиакомпонент FIREBALL CMS.

Он предназначен для:

- обычного видео;
- аудио;
- HLS VOD;
- HLS LIVE;
- камер;
- медиаблоков редактора.

FirePlayer умеет автоматически определять тип источника и режим воспроизведения.

Поддерживаются:

- native HLS в Safari и iOS;
- hls.js в остальных поддерживаемых браузерах;
- LIVE/VOD auto-detection;
- автоматическое восстановление HLS;
- управление live edge;
- reconnect;
- fullscreen;
- Picture-in-Picture;
- playback speed;
- volume persistence;
- video zoom и pan;
- keyboard/touch управление;
- диагностическая панель для Creator;
- корректное освобождение ресурсов после закрытия камеры.

Пример:

```html
<div
    class="fire-player"
    data-src="https://media.example.com/stream/index.m3u8"
    data-poster="https://media.example.com/poster.jpg">
</div>
```

JavaScript API:

```javascript
const player = new FirePlayer('#player', {
    src: '/media/movie.mp4',
    poster: '/media/movie.jpg'
});

await player.ready;
await player.play();

player.pause();
player.mute();
player.goLive();
player.fullscreen();
player.pictureInPicture();
player.destroy();
```

Полная документация:

[`docs/fireplayer.md`](docs/fireplayer.md)

---

## 🔔 Уведомления

CMS использует единый `NotificationService` для ядра и плагинов.

Уведомление может одновременно существовать внутри сайта и при разрешении пользователя доставляться через Web Push.

Пример:

```php
use App\Services\NotificationService;

NotificationService::create([
    'user_id' => $userId,
    'title' => 'Новая заявка',
    'message' => 'Поступила новая заявка от клиента',
    'type' => 'support_ticket',
    'action_url' => '/admin/support/requests',
    'source' => 'support',
    'priority' => 'normal',
]);
```

Система поддерживает:

- внутреннюю ленту уведомлений;
- непрочитанные уведомления;
- переход к источнику;
- Web Push;
- несколько устройств одного пользователя;
- удаление невалидных Push-подписок;
- fallback на внутреннее уведомление;
- интеграцию уведомлений с плагинами.

---

## 📱 PWA

PWA является системной возможностью FIREBALL CMS и не требует поддержки со стороны конкретной темы.

Реализованы:

- динамический `/manifest.webmanifest`;
- Service Worker;
- standalone-режим;
- offline fallback;
- favicon;
- Apple Touch Icon;
- maskable icons;
- safe-area;
- VAPID;
- Web Push;
- несколько Push-подписок на одного пользователя;
- пользовательское управление уведомлениями;
- тестовый Push;
- синхронизация непрочитанных уведомлений;
- поддержка Badging API на совместимых платформах.

Для Web Push в production требуется **HTTPS**.

Документация:

[`docs/ru/pwa/introduction.md`](docs/ru/pwa/introduction.md)

---

## 📊 Аналитика

Встроенная аналитика включает:

- посещения;
- просмотры страниц и записей;
- популярный контент;
- последних посетителей;
- источники переходов;
- географическую статистику;
- GeoIP через MaxMind;
- работу без установленной GeoIP-базы;
- визуализацию данных в панели обзора.

---

## 🔐 Безопасность

В FIREBALL CMS реализованы:

- CSRF-защита;
- middleware авторизации;
- роли пользователей;
- роль Creator;
- двухфакторная аутентификация;
- recovery codes;
- безопасное восстановление пароля;
- проверка прав критических действий;
- Rate Limiter;
- безопасная загрузка файлов;
- защита административных маршрутов;
- журналирование событий;
- ограничения на удаление Creator и последнего администратора;
- серверная валидация данных.

---

## 🌍 Локализация

Интерфейс CMS поддерживает:

- 🇷🇺 Русский;
- 🇬🇧 English;
- 🇩🇪 Deutsch;
- 🇨🇳 简体中文.

Системные переводы находятся в:

```text
app/Languages/
```

Плагины хранят свои переводы независимо:

```text
plugins/{plugin-slug}/lang/
```

Это позволяет устанавливать и обновлять плагины без добавления их переводов в ядро CMS.

---

## 🔄 Центр обновлений

FIREBALL CMS содержит встроенный `UpdateCenter`.

Он поддерживает два источника обновлений:

**Stable**

Использует опубликованные GitHub Releases.

**Developer**

Использует актуальное состояние ветки разработки.

Система обновлений умеет:

- проверять доступность новой версии;
- сравнивать версии;
- скачивать update package;
- проверять `update.json`;
- защищать пользовательские директории;
- создавать резервную копию перед обновлением;
- обновлять системные файлы;
- запускать миграции;
- мигрировать установленные плагины;
- очищать runtime cache;
- работать с Git-установками;
- сохранять commit для rollback;
- отдельно обновлять плагины.

Draft и prerelease GitHub Releases не устанавливаются через Stable-канал.

Перед production-обновлением рекомендуется создать дополнительную внешнюю резервную копию.

### Composer

Composer используется для первоначальной установки PHP-зависимостей.

Во время обновления CMS проверяет изменения:

```text
composer.json
composer.lock
```

Если зависимости уже присутствуют в `vendor/` и соответствуют lock-файлу, повторный запуск Composer не требуется.

Если зависимости изменились и `vendor/` им не соответствует, сервер должен иметь возможность запустить Composer.

---

## ⚙️ Системные требования

### Сервер

- PHP **8.2+**;
- MySQL **5.7+** или MariaDB **10.5+**;
- Apache с `mod_rewrite` или Nginx;
- HTTPS для production PWA/Web Push;
- Composer для установки и изменения PHP-зависимостей.

### PHP extensions

Рекомендуется наличие:

```text
pdo
pdo_mysql
mbstring
json
fileinfo
openssl
curl
zip
gd или imagick
```

`ZipArchive` используется системой резервного копирования и обновления.

---

## 🚀 Установка

Клонируйте репозиторий:

```bash
git clone https://github.com/Samkmv/FIREBALL_CMS.git
cd FIREBALL_CMS
```

Установите зависимости:

```bash
composer install --no-dev --optimize-autoloader
```

Настройте web root на:

```text
/public
```

Убедитесь, что веб-сервер может записывать данные в необходимые runtime-директории:

```text
storage/
public/uploads/
tmp/
```

После этого откройте сайт в браузере.

Если FIREBALL CMS ещё не установлена, будет запущен мастер:

```text
/install
```

Мастер первоначальной установки позволяет настроить:

- язык;
- базу данных;
- название сайта;
- URL;
- часовой пояс;
- первого пользователя Creator;
- системные таблицы;
- локальный конфигурационный файл.

---

## 🗂 Структура проекта

```text
FIREBALL_CMS/
├── app/          Контроллеры, модели, сервисы, views и языки
├── bin/          CLI и вспомогательные исполняемые инструменты
├── config/       Конфигурация, маршруты, версия и миграции
├── core/         Ядро CMS
├── database/     Схема БД и миграции
├── dist/         Файлы сборки и распространения
├── docs/         Документация
├── helpers/      Общие helper-функции
├── plugins/      Плагины
├── public/       Public web root и assets
├── storage/      Runtime-данные, логи и backup
├── themes/       Темы оформления
├── tmp/          Временные данные
├── vendor/       Composer dependencies
├── composer.json
├── composer.lock
├── update.json
└── README.md
```

---

## 🧱 Архитектура

Упрощённо FIREBALL CMS разделена на четыре уровня:

```text
┌──────────────────────────────┐
│          Themes              │
│   Public UI / Theme API      │
├──────────────────────────────┤
│          Plugins             │
│ Hooks / Routes / Migrations  │
├──────────────────────────────┤
│        Application           │
│ Services / Models / Admin UI │
├──────────────────────────────┤
│            Core              │
│ Router / MVC / DB / Auth     │
└──────────────────────────────┘
```

Основная идея архитектуры:

> **ядро предоставляет инфраструктуру, темы отвечают за отображение, плагины — за дополнительную бизнес-логику.**

---

## 📚 Документация

Документация проекта находится в каталоге:

[`docs/`](docs/)

Некоторые основные разделы:

- [FirePlayer](docs/fireplayer.md)
- [Editor 2.0](docs/editor-2.md)
- [Editor 2.0 Audit](docs/editor-2-audit.md)
- [Mobile Table Cards](docs/mobile-table-cards.md)
- [Performance](docs/performance.md)
- [Admin UI 2 Components](docs/admin-ui-2-components.md)
- [Темы — RU](docs/ru/themes/)
- [Themes — EN](docs/en/themes/)
- [Themes — DE](docs/de/themes/)
- [Themes — ZH-CN](docs/zh-cn/themes/)

---

## 🛠 Разработка

После изменения PHP-классов можно обновить autoload:

```bash
composer dump-autoload
```

Проверить PHP-файл:

```bash
php -l path/to/file.php
```

При разработке расширений рекомендуется:

- не изменять ядро из плагинов;
- использовать Plugin API;
- использовать Theme API для публичного интерфейса;
- валидировать входные данные на сервере;
- использовать CSRF для изменяющих запросов;
- экранировать пользовательский вывод;
- хранить переводы плагина внутри самого плагина;
- проверять desktop и mobile layouts;
- сохранять обратную совместимость публичных API.

---

## 📦 Версии

Версия движка определяется файлами:

```text
config/version.php
update.json
```

На момент обновления этого README:

```text
main:             1.8.0-beta.15
latest release:   1.8.0
PHP:              >= 8.2
```

Актуальные опубликованные версии:

[GitHub Releases](https://github.com/Samkmv/FIREBALL_CMS/releases)

---

## 📄 Лицензия

FIREBALL CMS распространяется по лицензии **Apache License 2.0**.

Полный текст:

[`LICENSE`](LICENSE)

---

<div align="center">

### 🔥 FIREBALL CMS

**Build the core once. Extend everything else.**

</div>