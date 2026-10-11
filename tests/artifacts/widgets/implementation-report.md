# Отчёт: система виджетов FIREBALL CMS

Дата: 11 октября 2026. Ветка: `widgets`. Изменения находятся в рабочем каталоге, коммит не создавался.

## Реализованные возможности

- Универсальный Widget API с реестрами типов и областей, контрактом виджета, репозиторием экземпляров и безопасным рендерером. Плагины регистрируют виджеты через `fireball_widgets_register`; документация содержит рабочий пример плагина из трёх файлов.
- Область `posts.sidebar` подключена к списку публикаций и отдельной публикации. Сохранены исходные категории, шесть свежих записей, социальные ссылки, активные ссылки, адаптивная сетка и мобильная Bootstrap-панель. Настройки и загрузчики данных общие для обоих представлений.
- Обязательные экземпляры защищены на сервере: их нельзя удалить, отключить, продублировать или перенести в другую область. Категории обязательны на обеих страницах; для остальных существующих блоков доступна настройка видимости по страницам.
- Дополнительные виджеты: последние публикации, погода и безопасный HTML. Несколько экземпляров имеют независимые настройки. Дополнительный виджет можно включить, выключить и удалить, сохраняя сам зарегистрированный тип.
- Категории: заголовок, собственный порядок и скрытие отдельных категорий. Глобальный порядок категорий в CMS не изменяется.
- Последние публикации: количество 1–20, категория, миниатюры, даты, порядок по дате, исключение текущей записи. Черновики, будущие публикации и записи, запрещённые текущему пользователю штатным фильтром доступа, исключаются.
- Погода: Open-Meteo на сервере, текущие условия, ощущаемая температура, влажность, ветер и прогноз 1–7 дней. Кэш, ограниченные тайм-ауты, повторное использование последнего прогноза при сбое и короткий кэш ошибок.
- Добавлен выбор местоположения: заданный город или геолокация браузера. При уже выданном разрешении погода обновляется автоматически; иначе посетитель использует кнопку «Погода рядом со мной». При отказе или ошибке остаётся город из настроек. Координаты округляются до сотых градуса и не записываются в настройки виджета; персональный ответ не кэшируется браузером как общедоступная страница.
- Погодные значки используют установленный Cartzilla Icons. Убрана серая emoji-плашка тумана; для тумана выбран `ci-wind`, для других условий — солнце, луна, облако, дождь, снег и гроза. Значки дней размещены в одинаковой центральной колонке 24 px.
- **Внешний вид → Виджеты**: добавление, AJAX-сохранение, перетаскивание мышью, перемещение указателем touch и кнопками, настройка видимости, уведомления и защита от сохранения устаревшей конфигурации.
- Настройки оформлены общими цветами и компонентами админки: карточки с иконками, раскрываемые панели, переключатели, оформленные Choices-списки, кнопки сохранения и удаления. На телефоне поля располагаются в одну колонку. После AJAX-обновления старые компоненты освобождаются, а несохранённые значения соседних форм сохраняются.
- Локализация ru/en/de/zh-cn, штатная авторизация и CSRF, валидация настроек, экранирование, ограничения безопасного HTML, изоляция ошибки отдельного виджета и ошибки регистрации плагина. Общий снимок конфигурации и мемоизация загрузчиков не добавляют запрос на каждый экземпляр.

## Миграции и текущее подключение

Добавлена `database/migrations/20261011_create_widgets.sql`: таблицы `widget_instances` и `widget_state`, уникальный системный ключ и три начальных обязательных экземпляра. Повторное применение и инициализация не создают дубликаты.

В текущей MAMP CMS миграция виджетов применена штатным MigrationRunner. Проверены подключение к базе, открытие реальной `/admin/widgets` и публичный вывод погоды. Для адресного применения добавлен необязательный список файлов `MigrationRunner::run(null, ['20261011_create_widgets.sql'])`; обычный запуск миграций продолжает применять все ожидающие файлы. Во время исправления хранилища посторонняя ожидающая миграция чата не запускалась.

Обновлён штатный asset manifest, чтобы браузер получил новые стили и скрипты с новым номером версии.

## Выполненные проверки

| Проверка | Результат |
| --- | --- |
| PHP-интеграция на отдельной MySQL без TCP | 55 из 55 |
| Реальные HTTP-маршруты тестовой CMS, права, CSRF, AJAX, геолокация | 22 из 22 |
| Клиент геолокации с синтетическими данными и состояниями разрешений | 9 из 9 |
| Синтаксис изменённых PHP-файлов и тестов, PHP 8.2 | 48 из 48 |
| Синтаксис двух JS-файлов виджетов | без ошибок |
| `git diff --check` | без ошибок |
| Реальный Open-Meteo: Берлин и синтетические координаты центра города | прогноз доступен, кэш используется повторно |

Всего **86 автоматизированных функциональных проверок**. Дополнительно выполнены проверки в браузере на ПК и телефоне, в светлой и тёмной теме, включая сохранение порядка, собственную сортировку категорий, добавление/настройку дополнительных виджетов, оформление списков после AJAX-обновления и отсутствие переполнения при ширине 390 px.

Реальные координаты устройства не запрашивались. Успешная геолокация, отказ, недоступность Permissions API, тайм-аут и сбой провайдера проверены синтетическими тестами; реальные серверные запросы используют только заданные тестовые координаты. Мобильное перемещение проверено кнопками; физический touch-жест на устройстве не эмулировался.

Тестовые базы, серверы и временные файлы из `tests/.tmp/widgets-*` остановлены и удалены. Отчёты и изображения оставлены в `tests/artifacts/widgets/`.

## Ограничения

- На этом этапе подключена только `posts.sidebar`. Новые области регистрируются API и явно подключаются в шаблоне; дополнительные области не вставлялись в профиль, шапку и подвал.
- Темы с собственными шаблонами должны явно подключить область. Стандартная тема и её совместимые старые представления уже подключены.
- Геолокация требует поддержки браузером, безопасного контекста (HTTPS или localhost) и разрешения посетителя. Обратное определение названия города по координатам не добавлено: персональный прогноз подписан «Ваше местоположение».
- При неоднозначном названии геокодирование использует первый результат Open-Meteo; можно уточнить город и страну. При недоступности API показывается последний доступный прогноз в пределах срока хранения либо понятное сообщение.
- Пределы редактора: 100 экземпляров, 1–20 последних публикаций, 1–7 дней прогноза, 50 000 символов HTML. HTML допускает пассивную разметку, ссылки, изображения, таблицы и классы; PHP, скрипты, iframe, формы, обработчики событий, inline-стили и data-атрибуты удаляются.

## Файлы

Перечень ниже относится к этой задаче. Уже существовавшие изменения `.gitignore`, удаление `plugins/calendar.zip`, а также внешние изменения `config/version.php` и `update.json` сохранены и не включены в реализацию виджетов.

### Новые файлы Widget API

- [app/Widgets/AbstractWidget.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/AbstractWidget.php)
- [app/Widgets/WidgetAreaRegistry.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetAreaRegistry.php)
- [app/Widgets/WidgetConflictException.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetConflictException.php)
- [app/Widgets/WidgetContext.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetContext.php)
- [app/Widgets/WidgetHtml.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetHtml.php)
- [app/Widgets/WidgetInstanceRepository.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetInstanceRepository.php)
- [app/Widgets/WidgetInterface.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetInterface.php)
- [app/Widgets/WidgetManager.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetManager.php)
- [app/Widgets/WidgetRegistry.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetRegistry.php)
- [app/Widgets/WidgetRenderer.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/WidgetRenderer.php)
- [app/Widgets/Types/CategoriesWidget.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/Types/CategoriesWidget.php)
- [app/Widgets/Types/HtmlWidget.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/Types/HtmlWidget.php)
- [app/Widgets/Types/LatestPostsWidget.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/Types/LatestPostsWidget.php)
- [app/Widgets/Types/SocialWidget.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/Types/SocialWidget.php)
- [app/Widgets/Types/TrendingWidget.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/Types/TrendingWidget.php)
- [app/Widgets/Types/WeatherWidget.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Widgets/Types/WeatherWidget.php)

### Новые файлы интеграции и оформления

- [app/Controllers/WidgetsController.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Controllers/WidgetsController.php)
- [app/Services/WeatherService.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Services/WeatherService.php)
- [app/Views/themes/default/admin/widgets.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Views/themes/default/admin/widgets.php)
- [app/Views/themes/default/admin/widgets_editor.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Views/themes/default/admin/widgets_editor.php)
- [app/Views/themes/default/admin/widget_instance.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Views/themes/default/admin/widget_instance.php)
- [public/assets/default/css/admin-widgets.css](/Applications/MAMP/htdocs/FIREBALL_CMS/public/assets/default/css/admin-widgets.css)
- [public/assets/default/js/admin-widgets.js](/Applications/MAMP/htdocs/FIREBALL_CMS/public/assets/default/js/admin-widgets.js)
- [public/assets/default/js/widgets-weather.js](/Applications/MAMP/htdocs/FIREBALL_CMS/public/assets/default/js/widgets-weather.js)
- [themes/default/partials/widgets/categories.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/partials/widgets/categories.php)
- [themes/default/partials/widgets/html.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/partials/widgets/html.php)
- [themes/default/partials/widgets/post-list.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/partials/widgets/post-list.php)
- [themes/default/partials/widgets/social.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/partials/widgets/social.php)
- [themes/default/partials/widgets/weather-icon.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/partials/widgets/weather-icon.php)
- [themes/default/partials/widgets/weather.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/partials/widgets/weather.php)
- [database/migrations/20261011_create_widgets.sql](/Applications/MAMP/htdocs/FIREBALL_CMS/database/migrations/20261011_create_widgets.sql)
- [docs/ru/widgets-api.md](/Applications/MAMP/htdocs/FIREBALL_CMS/docs/ru/widgets-api.md)

### Изменённые существующие файлы

- [app/Controllers/PostsController.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Controllers/PostsController.php)
- [app/Models/Post.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Models/Post.php)
- [app/Services/MigrationRunner.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Services/MigrationRunner.php)
- [app/Views/themes/default/admin/nav.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Views/themes/default/admin/nav.php)
- [app/Views/themes/default/posts/index.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Views/themes/default/posts/index.php)
- [app/Views/themes/default/posts/show.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Views/themes/default/posts/show.php)
- [themes/default/templates/posts.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/templates/posts.php)
- [themes/default/templates/post.php](/Applications/MAMP/htdocs/FIREBALL_CMS/themes/default/templates/post.php)
- [config/routes.php](/Applications/MAMP/htdocs/FIREBALL_CMS/config/routes.php)
- [core/Plugins/HookManager.php](/Applications/MAMP/htdocs/FIREBALL_CMS/core/Plugins/HookManager.php)
- [helpers/helpers.php](/Applications/MAMP/htdocs/FIREBALL_CMS/helpers/helpers.php)
- [app/Languages/ru.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Languages/ru.php)
- [app/Languages/en.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Languages/en.php)
- [app/Languages/de.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Languages/de.php)
- [app/Languages/zh-cn.php](/Applications/MAMP/htdocs/FIREBALL_CMS/app/Languages/zh-cn.php)

### Исходники проверок

- [tests/run-widgets.sh](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/run-widgets.sh)
- [tests/widgets.php](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/widgets.php)
- [tests/widgets_bootstrap.php](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/widgets_bootstrap.php)
- [tests/widgets_http.php](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/widgets_http.php)
- [tests/widgets_http_checks.php](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/widgets_http_checks.php)
- [tests/widgets_weather_client.js](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/widgets_weather_client.js)
- [tests/widgets_weather_live.php](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/widgets_weather_live.php)

## Материалы проверки

- [automated-tests.json](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/automated-tests.json)
- [http-tests.json](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/http-tests.json)
- [client-tests.json](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/client-tests.json)
- [php-lint.json](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/php-lint.json)
- [weather-live.json](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/weather-live.json)
- [browser-ui-checks.json](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/browser-ui-checks.json)
- [admin-settings-dark.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/admin-settings-dark.jpg)
- [admin-settings-light.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/admin-settings-light.jpg)
- [admin-settings-mobile.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/admin-settings-mobile.jpg)
- [weather-icons-fixed.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/weather-icons-fixed.jpg)
- [weather-geolocation-mobile.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/weather-geolocation-mobile.jpg)
- [posts-desktop.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/posts-desktop.jpg)
- [post-desktop.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/post-desktop.jpg)
- [posts-mobile.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/posts-mobile.jpg)
- [post-mobile.jpg](/Applications/MAMP/htdocs/FIREBALL_CMS/tests/artifacts/widgets/post-mobile.jpg)
