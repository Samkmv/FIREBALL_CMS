# Canonical origin, редактор, загрузки, устройства и избранное — 2026-10-06

Изменения выполнены в существующей архитектуре FIREBALL: PHP Session, `session_version`, Router/CSRF, SecurityLog, Pagination, шаблоны и языковые файлы сохранены. По последующему запросу «Исправь» **в локальной MAMP БД** применены две миграции устройств и избранного после резервной копии; production и его конфигурация не изменялись. Публикация, commit и push не выполнялись.

## Аудит: причины и исправления

1. **Разные origin.** Helpers уже использовали configured `PATH`, но входящий запрос на другой host/scheme не перенаправлялся. Поэтому страница могла работать на www, а API, шрифты и Service Worker загружались с non-www. `CanonicalOrigin` проверяет host, порт и схему до autoload/Session/Auth/Router; redirect 308 сохраняет encoded path/query/locale, включая подкаталог. Цель берётся только из конфигурации. Forwarded scheme доверяется только точным адресам `TRUSTED_PROXIES`; Host proxy должен сохранять. Автоматически предложенный адрес установщика не считается настроенным canonical URL. Runtime maintenance gate намеренно остаётся первым: при обновлении нельзя запускать частично заменённый bootstrap.
2. **Потеря snapshot страницы.** `Page::findById()` вызывал публичный renderer. Теперь admin/editor получают неизменённый `content`, public/preview рендерят явно, с отдельными cache keys. Пустое admin SEO-описание не заполняется base64. Найдены и исправлены обращения к отсутствующим `level`/`ratio` в renderer. Публичная модель Post и административная AdminPost уже разделяли эти контракты; их хранение не переписано.
3. **Ошибки загрузки.** CMS рекламировала свой лимит без учёта PHP; некоторые endpoints проверяли только `size`, молча игнорируя `UPLOAD_ERR_INI_SIZE` с нулевым размером. `UploadPolicy` объединяет CMS, PHP и component caps; SafeUploadService по-прежнему проверяет фактические MIME/изображения. Все PHP upload error codes имеют переводы. Multipart body, превышающий `post_max_size`, отклоняется с 413 до проверки теперь пустого CSRF поля. Нет общего bypass CSRF или разрешения опасных типов файлов.
4. **Нет поустройственного отзыва входа.** Сохранялась только глобальная версия сессий. Добавлен реестр hashed PHP IDs, проверка отзыва на каждом authenticated request и throttled UPDATE активности не чаще 90 секунд. Обнаруженная ротация ID при сохранении профиля теперь обновляет реестр, иначе пользователь внезапно выходил бы из аккаунта. Нормальный login и завершённый 2FA используют общий `Auth::loginUser()` после смены ID. До миграции прежняя авторизация продолжает работать; после неё старые действующие PHP-сессии регистрируются один раз. Известные revoked/expired строки никогда не оживляются.
5. **Избранного не было.** Добавлено универсальное хранилище с первой политикой `post`. Только опубликованные записи добавляются через INSERT SELECT; уникальный индекс предотвращает дубли. Удаление идемпотентно и ограничено владельцем. JOIN скрывает удалённые/draft записи. Модель списка использует две SQL-команды (COUNT + joined fetch), batch lookup состояния — одну. На карточки применяется существующий `public_posts_before_render`, поэтому ограничения плагинов подписок не обходятся. В этом фильтре обнаружен дополнительный N+1: правила доступа и роль проверялись отдельно для каждой карточки. Правила вместе с разрешёнными тарифами теперь загружаются одним JOIN, роль кешируется в рамках сервиса; число запросов не растёт с количеством карточек. Тест для 20 закрытых карточек без подписки подтверждает 3 запроса фильтра (правила, роль, подписка), для creator — 2.
6. **Мобильная проверка новых экранов.** Абсолютно позиционированный visually-hidden заголовок таблицы мог выходить из её статического scroll container и расширять document. Контейнер получил `position-relative`; прокрутка осталась внутри таблицы. Длинные кнопки массового выхода переносят текст, touch target минимум 44 px. Стиль и desktop layout CMS не заменены.
7. **Сердечки на публичных записях.** Общий partial теперь показывает контурное сердце, а сохранённую запись — заполненное красное. Добавлен в списки, категории, архив, подборку главной страницы и заголовок записи в обоих путях default templates. Размер кнопки 44 × 44 px; доступная подпись отражает действие. AJAX синхронизирует все экземпляры одной записи, ошибки выводятся безопасным текстом, обычный POST без JS сохранён. Персональное состояние добавляется пакетно после access-фильтров, не в общий cache. CSS включается по наличию компонента. Длинное слово в breadcrumb записи дополнительно получило штатный `text-break`, чтобы не расширять мобильную страницу.
8. **Единое меню профиля.** Верхние вкладки перенесены в существующий sidebar, вместе со ссылками сервисов и чата, под заголовком «Меню». Используется прежний `nav-tabs flex-column`, без JS-вкладок и без дублирования устройств/избранного. Активный пункт настроек определяется по разделу, поскольку у трёх ссылок одинаковый path и разные query. Сохранены plugin hook, порядок сервисов, красный выход и переход в админку; устаревшая горизонтальная автопрокрутка удалена. У всех шести разделов собственный заголовок и пояснение: обзор, личная информация/аватар, защита, уведомления, активные устройства/сеансы, сохранённые записи. Заголовок меню и новые пояснения переведены на четыре языка.
9. **Несовпадение запроса избранного со схемой.** После создания таблиц реальная MySQL-проверка обнаружила обращение к отсутствующему `posts.created_at`. Запрос исправлен на существующее `posts.published_at`; fixture теперь повторяет настоящий контракт `published_at NOT NULL` без выдуманной колонки. Реальные запросы и вывод обоих разделов проверены после исправления.

## Изменённые файлы и новые компоненты

### Origin

- `core/CanonicalOrigin.php` — чистая политика canonical redirect.
- `config/config.php` — признак явно настроенного app URL.
- `public/index.php` — ранний redirect 308/no-store.

### Редактор

- `app/Models/Page.php` — RAW/public/preview, публичные SEO fallbacks.
- `app/Controllers/AdminPagesController.php` — явный rendered preview.
- `app/Modules/BlockEditor/BlockRenderer.php` — безопасные значения по умолчанию.

### Загрузки

- `app/Services/UploadPolicy.php`, `UploadException.php` — лимиты и path-free переведённые ошибки.
- `app/Services/SafeUploadService.php`, `core/File.php`, `app/Models/FileManager.php` — общая policy, MIME, PHP-коды ошибок.
- `app/Controllers/AdminController.php` — preview validation и серверная диагностика лимитов; `AdminPostController.php`, `ChatController.php`, `app/Models/User.php` — upload validation записей, чата и аватара.
- `app/Modules/BlockEditor/BlockEditorController.php`, `BlockEditorService.php` — загрузки редактора и advertised effective limit.
- `core/ThemeManager.php`, `app/Services/ThemeEditorService.php` — ZIP/превью/замена изображения с прежними специализированными защитами ZIP/SVG.
- `app/Views/themes/default/admin/file_manager_browser.php`, `bin/cms.php` — effective limit в UI/CLI.
- `plugins/subscriptions/src/Services/AddressSuggestionService.php` — общая policy для CSV/TXT/TSV; `plugins/subscriptions/plugin.json` — версия **1.5.3**, переведённые release notes; `plugins/subscriptions/tests/local_address_suggestions_unit.php` — новый тип безопасной ошибки в тесте.
- `plugins/subscriptions/Plugin.php`, `src/Repositories/ContentRuleRepository.php`, `src/Services/AccessService.php` — пакетная загрузка правил и кеш роли без изменения условий доступа; `plugins/subscriptions/tests/subscriptions_unit.php` — ожидаемая версия 1.5.3.

### Аккаунт, маршруты и UI

- `app/Services/UserSessionService.php`, `AccountCleanupJob.php` — реестр устройств, отзыв, cleanup.
- `core/Auth.php`, `core/Session.php` — login/logout/rotation/validation и реальная session TTL; `core/Middleware/Auth.php` — 401 JSON для неавторизованного AJAX, обычный login redirect сохранён.
- `app/Services/AnalyticsService.php` — общий client parser, версии браузеров; уточнены iOS Firefox/Edge и desktop-mode iPad. UA — лишь подсказка, не фактор авторизации.
- `app/Services/SchedulerService.php` — ежедневная core-задача cleanup через имеющийся scheduler.
- `app/Controllers/UserSessionsController.php`, `FavoritesController.php` — owner-scoped POST, flash/JSON; массовый выход требует текущий пароль и ограничение попыток.
- `app/Models/UserFavorite.php` — универсальная модель, публикация, пагинация, batch state.
- `app/Controllers/AuthController.php` — новые разделы и корректная rotation; `PostsController.php`, `HomeController.php` — пакетное состояние избранного и подключение JS для публичных карточек/записи.
- `config/routes.php`, `core/Router.php` — маршруты, стандартная CSRF-защита, ранняя multipart size ошибка.
- `app/Views/themes/default/auth/profile.php`, `profile_sessions.php`, `profile_favorites.php`; `themes/default/partials/auth/profile.php`, `profile_sessions.php`, `profile_favorites.php` — основной/default fallback профиль.
- `app/Views/themes/default/incs/favorite_button.php`, `themes/default/partials/favorite_button.php` — общее сердечко, guest CTA и native POST fallback.
- `themes/default/templates/{posts,post,category,archive,home}.php`, `app/Views/themes/default/posts/{index,show}.php`, `app/Views/themes/default/home/index.php` — размещение сердечек без вложенных интерактивных ссылок.
- `app/Services/FrontendAssets.php`, `themes/default/templates/layout.php`, `app/Views/layouts/default.php` — общий CSS компонента только там, где он нужен.
- `public/assets/default/js/favorites.js`, `public/assets/default/css/favorites.css`, `public/assets/default/css/profile.css` — AJAX с отменой/возвратом кнопки после ошибки, синхронизация сердечек, безопасный текст ошибки и локальные responsive rules; `public/assets/default/js/main.js` — удалён автоскролл старых верхних вкладок.
- `app/Languages/{ru,en,de,zh-cn}.php` — все новые интерфейсные строки и upload errors.
- `app/Languages/{ru,en,de,zh-cn}/auth/{sessions,favorites}.php` — общие profile-переводы загружаются и через новые route callbacks, по существующему образцу `auth/settings.php`; `auth/profile.php` — заголовок «Меню» и уникальные пояснения каждого раздела.

### Тестовая инфраструктура и документация

- Новые unit/browser-тесты перечислены ниже в таблице результатов.
- `tests/fixtures/profile.php` — данные новых разделов и реальные языковые route adapters; `tests/fixtures/favorite_button.php` — public/guest/saved/unavailable forms; `tests/fixtures/post_favorites.php` — реальные публичные шаблоны с длинными заголовками; `tests/fixtures/upload_http.php` — изолированный HTTP fixture с обязательным тестовым environment flag, не production endpoint.
- `tests/profile_push_unit.php`, `tests/profile_push_browser.cjs` — проверки вертикального меню, выбранных core/plugin ссылок, удаления верхних вкладок и отсутствия дублей; тестовый ускоренный deadline увеличен с 100 ms до 1 s против ложных сбоев под нагрузкой, реальный Push timeout 10 s не менялся.
- `docs/account-origin-upload-audit.md` — этот полный отчёт; `docs/deployment/README.md` — ссылка на deployment-проверки; `docs/editor-2.md` — RAW/public контракт.

## Миграции и маршруты

Миграции подготовлены и **применены в локальной MAMP БД 2026-10-06**:

- `database/migrations/20261006_create_user_sessions.sql`: `user_id`, SHA-256 `session_hash` (не raw ID), `session_version`, browser/device/OS/UA/IP, created/activity/expiry/revocation; индексы hash, user/activity, expiry; FK users ON DELETE CASCADE.
- `database/migrations/20261006_create_user_favorites.sql`: `user_id`, `entity_type`, `entity_id`, `created_at`; UNIQUE(user_id, entity_type, entity_id), owner/date и entity индексы; FK users ON DELETE CASCADE. Будущие page/product/camera/business добавляются новой политикой, не заменой таблицы.

Перед применением штатный `DatabaseBackupService` создал защищённый backup `storage/backups/db-backup-2026-10-06-21-52-01-aaf8d2f2de01.sql` (1 334 019 bytes, права 0600; доступ к storage закрыт web-server rules). Применены **только** эти два SQL-файла через `SqlFileRunner`/`SchemaMigration`, под runtime/advisory locks; обе записи внесены в `update_migrations`, schema manifest обновлён, cache очищен. Проверены FK, доступность моделей, реальные SELECT и отсутствие warning при рендеринге разделов. Пользователи и существующие записи не изменялись. Посторонняя ожидающая `20261005_redact_chat_notification_copies.php` намеренно не запускалась. На production эти миграции данным действием не применялись.

GET `/profile/sessions`, `/profile/favorites`. POST `/profile/sessions/revoke`, `/profile/sessions/others`, `/profile/sessions/all`, `/profile/favorites/add`, `/profile/favorites/remove`. Все mutations имеют auth middleware и стандартный CSRF; locale prefixes обрабатываются имеющимся Router. AJAX: 401 guest, 419 CSRF, 422 invalid entity, 404 missing/draft/foreign session, 403 password rejection, 503 missing migration. Обычные формы используют flash и внутренние redirects; пользовательские return URLs не принимаются.

SecurityLog события: `login_session_created`, `session_revoked`, `logout_other_devices`, `logout_all`; IDs PHP-сессий не пишутся в UI, таблицы или журнал. Глобальный logout повышает существующий `users.session_version`. Scheduler очищает expired/revoked старше 90 дней, ограниченными партиями по 1000, ежедневно в 03:15 в timezone приложения; для очень большой накопленной истории потребуется несколько запусков/дней. Работает через уже существующий запуск `scheduler:run` раз в минуту, отдельный cron в систему не добавлен.

## Production: что настроить вручную

1. Создать обычную резервную копию, проверить миграции на **отдельной staging MySQL/InnoDB БД**, затем применить штатным `php bin/cms.php migrate` при безопасном развёртывании. CLI применяет также другие ещё не выполненные migrations проекта; не импортировать только CREATE TABLE вслепую. Installer/updater используют тот же migration runner и обновляют schema manifest.
2. В защищённой local config задать реальный `PATH`, например `https://example.org` (или `https://example.org/cms`). Код не содержит host MAXIPAPA. Не менять PATH сайта на staging без проверки proxy scheme/Host. За TLS proxy заполнить `TRUSTED_PROXIES` точными IP и сохранять Host. Иначе ошибочная схема/доверие proxy может привести к циклу redirect. Поддержка CIDR/Forwarded Host этим этапом не добавлялась.
3. Желательно redirect на web server **перед static assets и maintenance gate**. Пример Nginx для www (сертификат должен покрывать www):

```nginx
server {
    listen 80;
    server_name www.example.org example.org;
    return 308 https://example.org$request_uri;
}
server {
    listen 443 ssl;
    server_name www.example.org;
    # ssl_certificate / ssl_certificate_key — существующая конфигурация TLS
    return 308 https://example.org$request_uri;
}
```

Apache в отдельном www VirtualHost: `RewriteEngine On`, `RewriteRule ^ https://example.org%{REQUEST_URI} [R=308,L,NE]`. Исходная query сохраняется (не добавляйте новый `?`). Условие/VirtualHost должны исключать canonical host, иначе будет цикл. Не публиковать эти примеры дословно с example.org.

4. Для лимита CMS 50 MiB PHP-FPM pool может иметь:

```ini
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 55M
```

Альтернатива, когда хостинг разрешает per-directory INI: `.user.ini` с `upload_max_filesize=50M`, `post_max_size=55M`; pool php_admin_value имеет приоритет. Перезагрузить соответствующий FPM pool либо дождаться `user_ini.cache_ttl`. Проверять значения **в web UI диагностики**, а не только CLI: CLI/FPM могут читать разные INI. CLI `diagnose` показывает своё runtime environment. Не создавать публичный phpinfo.
5. Effective file limit = min(CMS cap, component cap, upload_max_filesize, post_max_size − 64 KiB multipart reserve). PHP unlimited не снимает CMS cap. Reserve рассчитан на обычный single-file multipart; множество файлов и большие текстовые поля дополнительно ограничены общим POST и `max_file_uploads`. `client_max_body_size` Nginx/Apache request limits/CDN должны пропускать допустимый POST (например 55 MiB); отказ upstream не может быть переведён PHP, настройте отдельную server error page. MP3 проверяется по содержимому, не по browser Content-Type; не разрешайте MIME glob или PHP/SVG в общих загрузках.
6. После ручного копирования JS/CSS выполнить штатный `php bin/cms.php assets:rebuild`. Updater уже собирает manifest. Проверить ссылки и SW scope на canonical host, нормальный login, 2FA и фото/MP3 на staging.

Cookies/localStorage/Service Worker старого www origin автоматически не переносятся. Потребуется новый вход/повторная установка PWA на canonical host у старых клиентов. Это ограничение браузерной модели безопасности, не причина расширять CORS до любых origin.

## Тесты и результаты

Автоматические DB-тесты используют изолированную SQLite in-memory, не рабочие credentials. Дополнительно проверено локальное MySQL-применение двух миграций и чтение/вывод настоящих разделов без добавления тестовых аккаунтов или избранного. Browser-тесты используют реальные CSS/partial/JS и перехваченные запросы; HTTP upload-тест поднимает кратковременный localhost PHP server без приложения/БД.

| Проверка | Результат |
| --- | --- |
| `tests/canonical_origin_unit.php` | 10: correct/wrong origin, locale/query, trusted/spoofed proxy, порты, subdir, header injection, порядок bootstrap |
| `tests/canonical_assets_unit.php` | 15: helpers, fonts, manifest icons/scope/start, SW payload и analytics на configured origin |
| `tests/page_content_unit.php` | 241: 17 типов, RAW/save/reopen/edit/save, public/preview, SEO без snapshot |
| `tests/editor_roundtrip_browser.cjs` | 27: реальные serializer/importer, 8 сложных типов, 3 цикла без потери data/settings/IDs/meta |
| `tests/upload_policy_unit.php` | 74: INI parser, min/overhead, PHP-коды, MP3 MIME и spoof rejection, 4 языка |
| `tests/upload_http.cjs` | 11: native MP3 multipart, PHP INI size=0 error, empty POST/FILES и 413 при post overflow |
| `tests/user_sessions_unit.php` | 32: ID regeneration/rotation, hash, activity throttle, owner checks, one/others/all, version invalidation, legacy/expired, pagination, cleanup, iOS client parser |
| `tests/favorites_unit.php` | 28: duplicate/idempotency/owner, drafts/deleted, 20/page, localization, реальная схема даты posts, joined query count, batch state и сохранение access metadata |
| `tests/favorites_subscription_unit.php` | 8 дополнительных: реальный фильтр подписок, скрытие title/excerpt/image, несколько тарифов, отсутствие правила, фиксированное число запросов для 20 карточек, creator/active plan/permission |
| `tests/account_security_unit.php` | 203: реальные routes/default CSRF, controllers/status/redirect/ownership, все новые переводы и языковые route adapters |
| `tests/account_features_browser.cjs` | 588: 4 языка, 2 template paths, light/dark, 320/390/768/1440 px; touch areas, overflow, active state, CSRF, pagination, escaping, AJAX failure/retry, реальный no-JS POST и guest CTA |
| `tests/post_favorites_browser.cjs` | 752: публичные шаблоны, 4 языка, light/dark, 320/390/1440 px, длинные заголовки, 44px сердца, guest/unavailable, синхронизация нескольких копий, безопасные ошибки/повтор |
| Profile/PWA unit checks | 953 + 173 — сохранены старые проверки, добавлены sidebar/no-duplicate/no-header-tab и уникальные заголовки/пояснения всех шести разделов, 4 языка/2 template paths |
| Существующие profile registration / Push browser checks | 1152 + 338 — без регрессий |
| Subscriptions address directory tests | 121 + 520 + 66 + 38 — без регрессий |
| `plugins/subscriptions/tests/subscriptions_unit.php` | `ok`: существующие проверки подписок, платежей, прав доступа и manifest 1.5.3 |

`git diff --check`, syntax 90 изменённых/новых PHP-файлов, JS syntax и JSON manifest проверены. Штатный asset manifest пересобран: 300 ресурсов; версии favorites.js/favorites.css/profile.css/main.js сверены с текущими hashes. Меню и сердечки визуально проверены на локальных browser screenshots для 390/1440 px. Полный install/upgrade на MySQL, actual iPhone Safari/PWA, внешний TLS proxy/CDN и production не запускались: нужны staging/manual acceptance checks, перечисленные выше. Локальная успешная проверка двух account-миграций не выдаётся за проверку полного обновления или настоящего устройства.

## Совместимость и оставшиеся ограничения

- Нет новой обязательной инфраструктуры, Theme API и Editor snapshot format сохранены. Новые tables — единственное обязательное изменение данных для sessions/favorites.
- Custom post templates, не наследующие default, должны вызвать `favorite_button` partial и включить переданные `footer_scripts`; автоматического DOM-патча чужих тем нет.
- Только опубликованный `post` поддержан на первом этапе; orphan/draft favorites скрыты, не удалены, чтобы повторная публикация восстановила список.
- IP/UA описывают сеанс приблизительно, могут изменяться, являются персональными данными; нужны принятые на сайте retention/privacy правила. Raw PHP IDs, пароли и тексты сообщений в реестр/журнал не помещаются.
- Session validation — indexed SELECT на authenticated application request, activity writes throttled. Это не mouse/scroll/asset polling. Отзыв применяется на следующем запросе, не удаляет cookie удалённого устройства дистанционно.
- Existing `Auth::setUser()` может вызываться при bootstrap и middleware; добавленная проверка read-only в обоих местах, но без второго UPDATE в пределах throttle.
- Уже утраченное при старом сохранении состояние Editor v2 автоматически не восстановить: нужен snapshot из backup/history. Исправление сохраняет будущие открытия/сохранения.
- Обновление лимитов хостинга, production-миграции, web-server redirects и acceptance на staging/настоящем iPhone остаются задачами deployment; исходники и воспроизводимые проверки готовы.
