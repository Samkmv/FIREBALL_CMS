# FirePlayer 1.1.0 — отчёт о доработке

Дата: 26 сентября 2026. Изменения выполнены в локальной ветке `main`.
Публикация на GitHub и установка на production в рамках этого прохода не выполнены.

## Результат

- Добавлен централизованный переход состояний и отдельные пользовательские статусы.
- Исправлены восстановление после потери сети, сохранение намерения Play и запрет автоматического запуска после Pause.
- Лимит восстановления больше не сбрасывается от одного успешного Play. Для сброса требуется устойчивый прогресс.
- LIVE проверяет декодированные кадры, а не только время. Пауза, скрытая вкладка и подготовка native HLS исключаются из проверки.
- Общий wake учитывает потребителей и полный адрес источника. Последний отменённый потребитель прекращает запрос.
- Добавлена серверная координация проверки камеры, короткий положительный кеш и структурированные ответы.
- Ограничены разрешённые источники, сегменты, редиректы и объём HTTP-ответов; исправлена обработка подписанных query-параметров.
- Добавлены качество hls.js, субтитры, ru/en/de/zh-cn, расширенная диагностика и audio metadata/seekto.
- Пауза managed-камеры освобождает источник через 60 секунд; закрытие окна камеры освобождает его сразу при начале закрытия.

## Lifecycle

`lazy → detecting → waking → connecting → ready → playing`

Из воспроизведения возможны `buffering`, `paused`, `reconnecting`, `offline`, `ended`, `error`.
Блокировка autoplay переводит в `awaiting-gesture`; уничтожение — в `destroyed`.

Холодная камера проходит readiness до подключения источника. Тёплая использует положительный
кеш до 5 секунд. Восстановление сначала локальное, затем пересоздание движка; повторные сбои
могут запросить wake. Попытки ограничены. Одновременные PHP-запросы не выполняют независимые
тяжёлые проверки одного и того же источника.

Для native Safari сохранена цепочка `wake ready → src → play`, без обязательного ожидания
metadata/canplay. После NotAllowedError допускается одна попытка без звука. Временная тишина
не сохраняется в настройках и снимается явным действием пользователя.

## Выполненные проверки

| Команда | Фактический результат |
| --- | --- |
| `node tests/fireplayer_native_startup.cjs` | 12/12 групп |
| `node tests/fireplayer_reliability.cjs` | 12/12 групп, включая 100 циклов с подсчётом Hls, таймеров и доступных обработчиков |
| `php tests/fireplayer_backend.php` | 16 проверок, включая локальный HTTP-сервер и последние сегменты |
| `node tests/fireplayer_browser.cjs` | Chromium 130 и Firefox 131 прошли DOM smoke с подставным транспортом/декодером |
| `node --check` для изменённых JS и тестов | Без синтаксических ошибок |
| `php -l` для изменённых PHP и теста | Без синтаксических ошибок |
| `git diff --check` | Без ошибок пробелов/патча |

Node использовался из доступного runtime. Playwright 1.48.2 и браузеры установлены отдельно
в `/private/tmp`; зависимости CMS не менялись. Для браузерного запуска использовались
`NODE_PATH=/private/tmp/fireplayer-browser-runtime/node_modules` и
`PLAYWRIGHT_BROWSERS_PATH=/private/tmp/fireplayer-browsers`.

Важно: браузерный прогон прошёл до последних небольших изменений (обработка paused HLS error,
расширение определения managed stream, коды ошибок, версия и фиксация выбранного субтитра).
Повторный финальный запуск был **не выполнен**: система разрешений отказала из-за исчерпания
лимита проверки разрешений, а не из-за небезопасности теста. JavaScript regression suite после
основных финальных правок прошёл. Нельзя считать финальный браузерный повтор подтверждённым.

## Legacy Plyr

Plyr не удалён: сохранены provider embeds и специальные сценарии. В обоих legacy-init
остаются защитные проверки владельца FirePlayer; добавлено исключение `data-player-native`.
Автоматический переход на FirePlayer расширен на явно размеченные direct media вне post-content.
Дублирующий HLS-код Plyr не запускается для элементов, уже принадлежащих FirePlayer; сам код
сохранён для оставшихся legacy consumers. Полная замена всех специальных интеграций не заявляется.

## Полный список файлов этого прохода

- `app/Controllers/StreamController.php`
- `app/Services/FrontendAssets.php`
- `config/streams.php`
- `helpers/helpers.php`
- `plugins/camera-manager/assets/camera-manager-player.js`
- `public/assets/default/js/fireplayer.js`
- `public/assets/default/js/fireplayer-hls.js`
- `public/assets/default/js/fireplayer-live.js`
- `public/assets/default/js/fireplayer-video.js`
- `public/assets/default/js/fireplayer-audio.js`
- `public/assets/default/js/fireplayer-diagnostics.js`
- `public/assets/default/js/fireplayer-init.js`
- `public/assets/default/js/plyr-init.js`
- `themes/default/assets/js/plyr-init.js`
- `tests/fireplayer_native_startup.cjs`
- `tests/fireplayer_reliability.cjs`
- `tests/fireplayer_backend.php`
- `tests/fireplayer_browser.cjs`
- `docs/fireplayer.md`
- `docs/fireplayer-hardening-report.md`

CSS и шаблоны подключения изучены, но в этом проходе не изменялись: новые настройки используют
существующие стили. Посторонние найденные `.bak` не удалялись; новых резервных копий не создавалось.

## Ограничения и приёмка

### Исправление регрессии разрешённых адресов

На странице `/posts/tehnicheskaya-zapis-dlya-razrabotchikov` подтверждён отказ
`INVALID_HLS_URL` для `https://cam.maxipapa.ru/rtsp/stream-33-01/index.m3u8`.
Причина: опубликованные записи используют текущий и старый HLS-хосты независимо
от выбранного адреса Camera Manager. Оба конкретных base URL добавлены в
`config/streams.php`: `https://cam.maxipapa.ru/rtsp` и `https://rtsp.ddns.net/rtsp`.
После исправления реальный endpoint для 33-01 вернул `ready: true`, `code: READY`.
В backend suite добавлены три проверки опубликованных хостов и отклонения похожего
постороннего домена; все 19 проверок прошли. Проверка в тестовом Chromium подтвердила
HTTP 200 для HLS-манифеста, но декодирование остановилось на `bufferAddCodecError`;
успешный показ реальных кадров этим прогоном не подтверждён.
Отдельная проверка MediaSource в этом Chromium вернула false для трёх профилей
H.264 и HEVC (при true для VP9). Поэтому этот тестовый бинарник не подходит для
подтверждения декодирования таких камер; проверка в обычном браузере остаётся нужна.

Это реализация reliability-pass с автоматическими проверками, **не сертификат готовности
production на всех устройствах**. Обязательный чек-лист находится в `docs/fireplayer.md`.

- Нужны реальные iPhone/iPad/macOS Safari, Android Chrome/Firefox, длительный LIVE и MAXIPAPA.
- Финальный повтор браузерного suite остаётся невыполненным из-за лимита разрешений.
- Нужна проверка светлой/тёмной темы и жестов на реальном телефоне.
- DASH adapter не добавлен; неподдерживаемый формат получает UNSUPPORTED_FORMAT.
- HLS quality доступно только hls.js; native ABR управляется браузером.
- Некоторые native метрики недоступны и показываются как прочерк.
- Координация PHP требует общей файловой системы с работающим flock, а не независимых кешей разных серверов.
- Для источников вне Camera Manager необходимо заполнить `allowed_hls_bases`; редиректы не выполняются.
- Старый HTML с настоящим `video src` может начать загрузку до исполнения JavaScript. Для гарантии нулевых запросов до Play используйте managed-разметку `data-src` без заранее подключённого media src.
- Полный прогон всех перечисленных в задании комбинаций устройств, VOD persistence, переходов сети и 30-минутных потоков не выполнен; покрытие тестов указано выше без утверждения о полной сертификации.
