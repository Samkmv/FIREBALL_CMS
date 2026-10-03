# Theme System — Full Public UI Coverage

Основание: локальный `main` и свежий `origin/main` совпадают на `b40c385`.
Рабочая ветка: `codex/theme-full-public-coverage`. В начале рабочая директория
содержала чужое изменение `plugins/calendar.zip`; оно не изменялось этой работой.
Инструкций `AGENTS.md` в репозитории и родительских каталогах не найдено;
прочитаны README и существующие документы Theme API/создания темы.

## Аудит публичного HTML

| Маршруты | Контроллер/источник | Шаблон Theme API |
| --- | --- | --- |
| `/`, варианты главной (страница/записи) | HomeController | home / page / posts; уже тематические |
| `/{slug}` | PagesController | page; уже тематический |
| `/posts`, `/posts/{slug}`, `/category/{slug}`, `/archive` | PostsController | posts / post / category / archive; уже тематические |
| `/search` | SearchController | search; уже тематический |
| `/contacts`, GET/POST | HomeController | contacts |
| `/support`, GET/POST; `/support/articles` | HomeController | support |
| `/support/articles/{slug}` | HomeController | support_article |
| `/login`, GET/POST | AuthController | auth/login, включая форму восстановления |
| `/two-factor-challenge`, `/two-factor-recovery`, GET/POST | AuthController | auth/two_factor_challenge / auth/two_factor_recovery |
| `/reset-password`, `/register`, GET/POST | AuthController | auth/reset_password / auth/register |
| `/profile`, `/profile/settings`, GET/POST | AuthController | auth/profile / auth/settings |
| `/chat`, `/chat/group` | ChatController | chat/index / chat/group |
| `/offline` | PwaController | offline |
| Неизвестные публичные адреса | abort / Router | 404 → системный резерв при сбое |
| `/calendar` | CalendarController::viewer | plugins/calendar/calendar |
| `/toy-car-rental` | routes.php | plugins/toy-car-rental/frontend |
| `/subscriptions`, `/subscriptions/plans` | PublicController::plans | plugins/subscriptions/public/plans |
| `/subscriptions/checkout/{id}` | PublicController::checkout | plugins/subscriptions/public/checkout |
| `/account/subscription` | PublicController::account | plugins/subscriptions/public/account |
| `/profile/subscription-details` | PublicController::profile | plugins/subscriptions/public/profile |
| `/subscriptions/robokassa/success`, `/subscriptions/robokassa/fail`, GET/POST | PublicController::success/fail | plugins/subscriptions/public/payment-result |
| `/profile/vpn-v2`, `/{id}`, `/instructions/{platform}` | ProfileVpnController | plugins/vpn-manager-v2/public/my-vpn |

Проверены также вложенные `routes/public.php`, `admin.php`, публичные и административные
контроллеры плагинов. Camera Manager не содержит собственной публичной HTML-страницы;
его media/asset endpoints оставлены служебными.

Публичный layout остаётся Cartzilla и вызывает тематические `menu`, `header`,
`footer`; меню использует `menu-links`, header — `mini-cart`.
Профиль использует `auth/profile` и четыре partials разделов. Поля паролей —
`password_field`, стартовая геометрия чата — `chat/viewport`.
Публичная таблица подписок использует `plugins/subscriptions/table`,
`table_footer`, `responsive_table_cards`; VPN — `plugins/vpn-manager-v2/happ-routing-link`.
Все эти зависимости разрешаются через Theme API независимо от родительского шаблона.
CSS профиля, password-field.js, viewport.js и актуальные JS/CSS чата включены
в default theme. Общий main.js синхронизирован с текущими legacy-функциями
профиля, уведомлений и публичного UI. Формы, CSRF, переводы, валидация, SEO,
данные контроллеров и plugin hooks сохранены.

## Исключения и системные ответы

- `/install`: отдельный установщик, legacy view/layout; не зависит от установленной темы.
- `/admin/pages/preview/{id}` и `/admin/posts/preview/{id}`: защищённые предпросмотры
  контента через legacy `pages/show`, `posts/show`. Шаблон админки не изменён.
- `/admin/themes/preview/{slug}`: защищённый редирект на публичный preview Theme API;
  выбор preview-темы разрешён только `check_admin()`.
- `/admin/block-editor/preview`: JSON с HTML блока; не превращается в тематическую страницу.
- `/product/{slug}`: существующая демонстрационная строка `Product {slug}`, без HTML layout.
- 403/500 и резерв 404: системные error views; при недоступном/сломавшемся системном
  view используется минимальный HTML без внутренних подробностей ошибки.
- POST `/forgot-password`, `/logout`, сброс 2FA recovery, действия профиля/плагинов:
  существующие редиректы/JSON сохранены; отдельные HTML-страницы не добавлялись.
- API, PWA manifest/service worker, SSE чата, сообщения/typing/media, уведомления,
  search suggest, корзина JSON, feedback поддержки, VPN subscription/token endpoint,
  payment webhook, asset endpoints и загрузки не переводились на тематический layout.

## Реализация и совместимость

Новые публичные страницы переопределяются файлами `templates/...`.
`plugin_view()` сохраняет сигнатуру и режим фрагмента; публичные вызовы используют
Theme API, административные сохраняют прежний layout независимо от роли посетителя
публичной страницы. При отсутствии plugin override используется оригинальный файл
плагина. Все встроенные публичные представления установленных плагинов включены
в diagnostics; произвольные сторонние представления доступны через resolvePluginFile.

Генератор создаёт полноценный posts.php. Обязательный минимум старых тем не расширен.
`resolveFile()` используется и рендерингом, и диагностикой, отдельно для layout,
шаблонов и partials. Diagnostics требует `check_admin()` и показана в существующих
карточках `/admin/themes`, без изменения shell и редактора.

Ошибки отсутствующего template/layout теперь бросают исключение вместо вложенного
abort. Буферы и content восстанавливаются при сбое. Тематическая 404 сохраняет
HTTP 404 даже если шаблон меняет статус; её сбой безопасно переключает на системный
резерв. Traversal и symlink на файл или каталог вне темы отвергаются.
Versioned assets используют физический файл после fallback и существующий manifest;
проверка файла повторяется при обращении, чтобы не доверять устаревшему результату
после замены файла symlink. Для отсутствующего безопасного asset сохраняется URL,
но assetPath не выдаёт путь к несуществующему или небезопасному файлу.

## Проверки

- `tests/theme_coverage.php`: реальная генерация и содержимое posts.php; старый минимум;
  все 21 публичный override; default coverage; независимые template/layout/partial
  fallback; diagnostics и запрет гостю; отсутствие обоих файлов; broken PHP и чистые
  буферы; plugin original/override, legacy admin и layout=false; traversal и symlink,
  включая замену ранее разрешённого asset; version из fallback manifest.
- Отдельные процессы 404: тематический успех, отсутствующий template, ошибка template,
  ошибка partial, ошибка/отсутствие layout, отсутствие default, отсутствие system view.
- `tests/profile_settings.php`: actual default-theme profile/settings markup;
  формы разделены, безопасность паролей/CSRF, recovery, подписки и настройки сохранены.
- Существующие plugin update (43 проверки), downloads block, FirePlayer backend,
  app/chat viewport и chat selection guard проходят.
- Subscriptions account view (424 проверки), profile checkout (121), Calendar unit проходят.
  Исправлено устаревшее XPath-ожидание account-теста: форма отмены продления находится
  в модальном окне вне article. Прежний тест падал на том же assertion и на исходном main.
- VPN profile assets, summary, Happ routing (36 assertions), stage27 проходят.
- PHP 8.2 lint всех 47 изменённых/новых PHP файлов и `git diff --check` проходят.

Smoke GET выполнены на временном локальном PHP-сервере для home/posts/archive/search,
contacts/support, login/register/reset/2FA, offline, profile/settings, chat/group,
admin, API menu, manifest и неизвестного адреса. Все запросы останавливаются при
инициализации приложения с `SQLSTATE[HY000] [2002] No such file or directory`:
локальный MySQL socket недоступен. До контроллеров запросы не доходят, поэтому это
не является успешной проверкой живых страниц, API или админки.

Browser geometry тест обновлён для theme assets, но не может запуститься:
локальный Playwright Chromium executable отсутствует. Ничего не устанавливалось.
Авторизованные browser smoke, POST login/CSRF/валидация в живой базе, визуальная QA
и реальная админская диагностика остаются для окружения с работающей MySQL и браузером.
Изменения не слиты в main, не отправлены и не развёрнуты на сервере.
