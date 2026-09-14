# Заключительный performance-проход — 14 сентября 2026

Основа: `main`, `9fad4c86807f37dce481909b7a0da003559af27e`, версия 1.8.0-beta.13. Предыдущий отчёт `performance/README.md` описывает предыдущий этап; его цифры не являются замерами этого патча.

## Cache и cross-worker invalidation

`set(A)` и `remove(A)` публикуют атомарную ревизию только A в `<md5>.rev`. APCu-ключ содержит namespace установки, generation, ID и ревизию. Новый PHP-запрос читает ревизию перед первым L2 lookup: старое значение в другом FPM worker или отдельном CLI/APCu-сегменте становится недоступным. B сохраняет свой L2 hit. Нужен общий каталог CACHE для процессов, которые разделяют данные.

L1 остаётся снимком на время запроса; повторные чтения не обращаются к файловой системе. Уже выполняющийся запрос может завершиться со своим L1-снимком; следующий запрос увидит изменение. Это не механизм синхронизации долгоживущих application workers.

`clear()` очищает файлы и L1 и меняет общую generation. APCu целиком не очищается: старые записи истекают максимум через 600 секунд. Writers используют общий shared lock и одну из 256 блокировок ключей; clear берёт exclusive lock. Файлы данных и ревизий публикуются через rename. Falsy values и TTL проверяются во всех слоях. Без APCu работает L1/file cache. Ревизии удалённых ключей сохраняются до clear, чтобы поздний reader не воскресил старый ключ.

## Постоянный AssetManifest

`storage/asset-manifest.json` хранит первые 20 символов SHA-256 содержимого. Public lookup один раз читает JSON и дальше использует request memory: без hash_file, обхода каталогов или проверки каждого asset. Повреждённый/отсутствующий manifest даёт URL без версии, без массового lazy hashing и без 500. Query string и fragment сохраняются. Cache clear не удаляет manifest.

Хеширование выполняется только при явной пересборке:

- `php bin/cms.php assets:rebuild` и CLI migrate;
- установка/обновление CMS, операции Update Center с обновлением runtime cache;
- установка/активация/обновление плагина;
- существующие hooks активации/импорта темы и файловых операций ThemeEditor;
- существующий `PwaService::syncIcons()` при сохранении PWA-настроек/иконок.

Пересборка сканирует static extensions внутри public/assets, public/uploads, themes, plugins. Медиафайлы видео/аудио не хешируются. Для нестандартных путей maintenance API принимает список roots. JSON и lock исключены из Git: пути локальны для установки. Нельзя переносить manifest между разными absolute deployment paths; его нужно построить на целевой машине. Publication: lock, temporary file в STORAGE, rename. Ошибка сборки оставляет ранее опубликованный manifest.

Изменение файлов вручную/через Git требует `assets:rebuild`. Upload API не изменён; новые обычные uploads до обслуживания используют допустимый URL без версии. Immutable HTTP contract существующих versioned URLs сохранён.

## FirePlayer

| Разметка | Дополнительные модули |
| --- | --- |
| Текст, native/background video | нет FirePlayer |
| Известный audio file | audio |
| MP4/известный video file | video |
| HLS с `data-mode="vod"` | video + hls |
| Live HLS или HLS с автоматическим режимом | video + hls + live |
| Явный `['player']`, динамическая/manual или неоднозначная legacy-разметка | совместимый полный набор |

Core, init, Plyr и compatibility bridge остаются общими для player-страниц. В обоих layouts video/audio/hls/live имеют отдельные условия. Административный fallback оставлен полным, поскольку плагины могут создавать плеер динамически. Diagnostics script подключается только при существующем разрешении `can_view_video_diagnostics()`.

Legacy upgrader переносит явный data-mode в wrapper. Loader `fireplayer-hls.js` не переписан: native HLS остаётся приоритетным, vendor hls.js загружается динамически при необходимости MSE fallback. MP4/audio не запрашивают этот модуль. Неоднозначный источник не классифицируется по одному наличию src: сохранён безопасный fallback.

Удалён preload cartzilla-icons.woff2 в обоих layouts. CSS/font-face и файл шрифта сохранены.

## Safe search rebuild

Provider API сохранён. `reindexProvider($name, bool $allowEmpty = false)` сначала полностью перечисляет документы в disk-backed tmpfile; RAM ограничена текущим документом (сам provider по-прежнему отвечает за потоковую выдачу своей коллекции). Exception, неверный тип документа или сбой SQL при получении данных не затрагивают старый индекс. Затем транзакция заменяет документы/токены и search_index_state; ошибка записи откатывает всё. Удаление старых ID выполняется партиями по 500.

Если новый набор пуст, а старый непуст, по умолчанию выбрасывается исключение. После проверки источника администратор может явно подтвердить пустоту через `reindexProvider($name, true)` или `php bin/cms.php search:reindex --allow-empty`. CLI-флаг подтверждает пустые результаты **всех** провайдеров данной команды: для одного провайдера предпочтителен maintenance API. Если старый индекс также пуст, пустой результат допустим без флага. Флаг не подавляет исключения/неверные документы. Полная пересборка требует собственной транзакции.

/search и /search/suggest не изменены: обслуживание не возвращено в GET.

## Контрольный HTTP benchmark

`bin/benchmark-http.py` требует Python 3 и curl с `%{json}`. Один запуск делает один запрос с timeout 15 секунд; без параллелизма, retries и следования редиректам. Вывод — JSON со статусом, TTFB, полным временем, полученными bytes и счётчиками Server-Timing. Без заголовка profiler будет null, а не выдуманные нули. Размер — тело ответа, без заголовков и зависимых CSS/JS/media.

Пример на staging:

```sh
python3 bin/benchmark-http.py https://staging.example.org '/search?q=test'
python3 bin/benchmark-http.py https://staging.example.org /admin --cookie-file /private/path/staging-cookies.txt
python3 bin/benchmark-http.py https://staging.example.org /api/analytics/track --method POST --data-file /private/path/analytics-fixture.json
```

Не передавать cookie jar в Git. POST выполнять только с согласованным тестовым payload в staging: реальное событие меняет аналитику. Скрипт не обходит auth/CSRF/валидацию.

Последовательно снять `/`, существующие public page, post, category, `/search?q=test`, `/login`, `/admin`, существующий plugin route, `/api/analytics/track` (POST), `/notifications/feed` с действующей сессией. Проверять статусы: 302 на /admin без сессии измеряет только auth gate. Повторить по одному запросу после прогрева, записать окружение и состояние кешей; это не p95/нагрузочный тест.

Для полных HTTP-метрик включить существующий `FIREBALL_PERFORMANCE_DEBUG=1` в окружении PHP **на закрытом staging**, затем выключить. Передача env только клиентскому curl не включает profiler на сервере. FPM может очищать переменные окружения: настроить передачу этой переменной для тестового pool. Не включать общий DEBUG ради benchmark.

`Server-Timing` содержит pdo_connections, sql_queries, ddl_queries, schema_queries, cache_file_reads, cache_l1_hits, peak_memory, а при соответствующих операциях asset_hashes/asset_manifest_scans и L2/revision counters. При наличии заголовка отсутствующие event counters означают ноль. CLI `php bin/profile-request.php '/search?q=test'` даёт отдельные PHP-метрики: app duration нельзя выдавать за HTTP TTFB.

### Локальная проверка

Сырые результаты: `performance/http-2026-09-14.json` (MAMP localhost:8888, один запрос на маршрут) и `performance/cli-2026-09-14.json` (PHP CLI 8.4, отдельные последовательные запросы). HTTP-profiler не включался, поэтому счётчики HTTP неизвестны. Локальный analytics POST содержал `{}` и проверяет accepted path; полноценную запись аналитики с public IP он не подтверждает. Admin/plugin/notifications без сессии проверяют только доступ. Эти данные не являются production baseline и не доказывают ускорение относительно предыдущего релиза.

В CLI-публичных маршрутах проверять: максимум 1 PDO, 0 DDL, 0 schema inspection, 0 asset hashes/scans. Поиск должен оставаться обычным чтением индекса. По review вызовы миграций и rebuild остаются только в maintenance. Сравнивать число SQL, file reads, peak memory и asset list на одинаковом наборе данных. Универсального TTFB для разных серверов не задаём.

## Сервер: следующий deployment check

CMS не меняет php.ini/FPM. Проверить `opcache.enable=1`; подобрать memory_consumption по занятой памяти прогретого кэша с запасом и max_accelerated_files по числу PHP-файлов с запасом роста. При validate_timestamps=0 deployment обязан обновлять OPcache/reload FPM; иначе оставить проверку timestamps. См. [официальную документацию OPcache](https://www.php.net/manual/en/opcache.configuration.php).

Выбор pm: dynamic для переменной нагрузки с готовыми workers, ondemand для редко используемых pools с допустимым cold-start, static для предсказуемого выделенного ресурса. Ориентир по памяти: `max_children <= floor(доступный бюджет FPM / измеренная память worker под нагрузкой)`, дополнительно ограничить CPU и пропускной способностью DB. Например, 2 GiB бюджета и 100 MiB на worker дают верхнюю оценку 20; это не рекомендация ставить 20 на любом сервере. Учитывать shared OPcache и остальные процессы отдельно, проверять RSS/PSS реальных workers: PHP peak_memory не равен RSS. pm.max_requests подбирать по наблюдаемому росту памяти; slowlog/request_slowlog_timeout — по целевому времени ответа и доступному объёму логов. См. [документацию FPM](https://www.php.net/manual/en/install.fpm.configuration.php).

## Аудит изображений: отдельная следующая задача

- `themes/default/templates/post.php`: обложка имеет размеры, eager/high priority и async; рекомендации имеют lazy/async, размеры и srcset/sizes. Сохранить при дальнейших изменениях.
- `themes/default/templates/home.php`: camera cards имеют размеры, lazy/async, srcset/sizes; обычный card img около строки 228 имеет lazy/async, но без размеров и responsive candidates. Следующий локальный шаг — добавить размеры и корректный sizes по реальной сетке.
- `app/Services/PostImageService.php` уже создаёт варианты WebP при поддержке GD и использует оригинал/fallback; AVIF-генерацию отдельно оценить по серверной поддержке, CPU и совместимости.
- Проверить пользовательский HTML/изображения тем вне карточек, размеры больших originals и попадание originals в маленькие блоки; оригиналы не заменять и не удалять.
- Отдельно проверить LCP hero/poster, camera thumbnails и freshness poster URL. Не применять lazy к LCP; thumbnails не должны запускать video download. Планировать преобразование при upload/background maintenance, не в ordinary GET.

Uploads/API и image pipeline в этом патче не изменены.

## Проверки и границы

Runtime tests покрывают Cache/falsy/TTL/revisions/L2, persistent manifest/failure/fallback/cache clear, player detection, search hot path и прежние schema guards. В CLI нет APCu: L2 проверен детерминированным двойником с реальными файлами ревизий и сбросом request memory. Реальный cross-FPM/APCu soak остаётся deployment-проверкой.

Disposable MySQL тест проверяет миграции и plugin update, замену документов, provider exception после первого yield, неверный документ, SQL failure, suspicious zero, authoritative empty, неизменность документов/токенов/state при отказе. Временная база удаляется в finally.

Физические Safari/iPhone, Firefox, Chrome/Yandex и Android, живой поток MAXIPAPA и авторизованные admin/plugin flows в этом окружении не проверены. Перед release выполнить smoke: native HLS на Safari, MSE fallback в остальных браузерах, autoplay/muted/poster, retry и live latency, разрешённые diagnostics; затем PWA update/offline и ru/en/de/zh-cn. JS HLS engine/recovery API не изменялся.

## Действия после обновления

На целевой установке выполнить `php bin/cms.php assets:rebuild` после размещения файлов; штатный Update Center делает это сам. Проверить права STORAGE/CACHE для CLI и FPM. Обычный cache:clear не требует повторного hashing. При ручном deployment обновить OPcache согласно политике сервера. Search reindex не обязателен для этого патча; не использовать --allow-empty автоматически. Затем снять staging baseline с реальными сессиями и выполнить browser/device smoke.

## Результаты тестов

| Команда | Результат |
| --- | --- |
| php tests/performance_runtime.php | 79 checks passed |
| php tests/search_schema_regression.php | 9 checks passed |
| node tests/background_requests.mjs | 34 checks passed |
| node tests/notification_polling.mjs | 38 checks passed |
| php tests/migration_integration.php --disposable-database | 24 checks passed; база удалена |
| php plugins/vpn-manager-v2/tests/stage22_unit.php | 7 cases, status ok |
| php plugins/vpn-manager-v2/tests/stage24_unit.php | 8 cases, status ok |
| PHP lint, все 13 изменённых PHP-файлов | без ошибок |
| node --check public/assets/default/js/fireplayer-init.js | без ошибок |
| git diff --check | без ошибок |

Всего 199 проверок/cases. Локальная команда MySQL использовала `-d pdo_mysql.default_socket=/Applications/MAMP/tmp/mysql/mysql.sock`; production этот путь не требуется. Проверка намеренного SQL failure пишет лог только во временный каталог теста.

## Точный состав патча

- `.gitignore`
- `app/Search/SearchIndexer.php`
- `app/Services/FrontendAssets.php`
- `app/Services/InstallService.php`
- `app/Services/SearchMaintenance.php`
- `app/Services/UpdateCenter.php`
- `app/Views/layouts/default.php`
- `bin/benchmark-http.py`
- `bin/cms.php`
- `core/AssetManifest.php`
- `core/Cache.php`
- `core/Plugins/PluginManager.php`
- `docs/performance.md`
- `docs/performance/cli-2026-09-14.json`
- `docs/performance/http-2026-09-14.json`
- `public/assets/default/js/fireplayer-init.js`
- `tests/background_requests.mjs`
- `tests/migration_integration.php`
- `tests/performance_runtime.php`
- `themes/default/templates/layout.php`
