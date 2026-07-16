# VPN Manager V2 — приёмочный отчёт этапа 13

Дата проверки: 16 июля 2026 года  
Проверяемая версия плагина: `0.12.0`  
CMS: FIREBALL CMS `1.7.2-beta.10`  
Среда: PHP `8.2.0`, MySQL `5.7.39`, MAMP/macOS

## Итог

VPN Manager V2 **не готов к миграции production-данных без дополнительных внешних приёмочных проверок**.

Серверная цепочка до 3x-ui, реальное создание/изменение/отключение/включение/удаление временного клиента, subscription endpoint, revision, ETag, ownership, настройки, уведомления и очистка локальных данных прошли проверку. Временный удалённый клиент и локальная acceptance-подписка удалены, исходное число клиентов inbound восстановлено.

Блокирующие непроверенные пункты:

- импорт subscription URL в реальное VPN-приложение и установление VPN-туннеля;
- реальный VLESS XHTTP inbound — в текущем 3x-ui есть только один VLESS TCP Reality inbound;
- реальная мультисерверная подписка — в V2 настроен только один физический сервер;
- Safari/WebKit на физическом iPhone;
- развёрнутая Linux production-среда и production reverse proxy/domain.

Старый VPN Manager не изменялся и не удалялся. Его запись в текущей локальной БД уже имела статус `inactive`; статус не менялся. Production на V2 не переключался. Общая шапка и бренд MAXIPAPA не менялись.

## Проверенная архитектурная цепочка

Фактически подтверждено:

1. CMS создаёт локальную подписку и node до HTTP-запроса.
2. ThreeXuiClient авторизуется штатным token из зашифрованного хранилища.
3. Клиент создаётся внутри правильного remote inbound.
4. Повторное чтение подтверждает UUID, email, subId, enable, expiryTime, totalGB, limitIp и Flow.
5. Subscription endpoint строит актуальный VLESS TCP Reality URI из текущих таблиц и inbound settings.
6. Редактирование срока, лимита, Flow и статуса подтверждается повторным чтением из 3x-ui.
7. После конфигурационного изменения увеличивается revision, очищается кэш и меняется ETag той же subscription-ссылки.
8. Полное удаление подтверждает отсутствие клиента в 3x-ui, затем удаляет локальные nodes и подписку; старый token возвращает `404`.

Не подтверждён последний участок `VPN-приложение → VPN-туннель → внешний адрес`. TCP-порт фактического inbound доступен, но это не считается проверкой VPN-соединения.

## Найденные и исправленные дефекты

### 1. Action-dropdown универсальных мобильных таблиц

Причина: partial мобильных карточек создавал `.admin-post-actions-dropdown`, но не добавлял `data-admin-post-actions-dropdown`. Общий JavaScript искал именно этот атрибут, поэтому не переносил меню в `body`, не позиционировал его относительно кнопки и не обновлял координаты при прокрутке вложенного контейнера.

Исправление: восстановлен публичный контракт универсального table component. После исправления:

- desktop: расстояние от кнопки до меню — 8 px;
- при прокрутке контейнера на 90 px кнопка и меню сместились на одинаковые 90 px;
- viewport 390×844: меню осталось внутри viewport, без горизонтального overflow;
- при мобильной прокрутке на 70 px кнопка и меню сместились на одинаковые 70 px.

### 2. Лишний revision/ETag при неизменившейся синхронизации inbound

Причина: InboundSyncService безусловно вызывал `touchByServer()` после любого успешного ответа 3x-ui. Даже идентичная повторная синхронизация увеличивала revision всех активных подписок сервера и меняла ETag без изменения конфигурации.

Исправление: InboundRepository сравнивает фактические поля и JSON текущего inbound с новым снимком. Revision меняется только при создании, изменении конфигурации/статуса или появлении/исчезновении inbound. `synced_at` продолжает обновляться при каждом успешном чтении.

Проверка после исправления: две реальные последовательные синхронизации вернули `config_changed=false`, число inbound осталось равным 1, дублей нет, revision обеих существующих подписок не изменился.

Примечание: первый диагностический прогон выполнялся до исправления и успел увеличить revision подписок `#1` и `#8` на 2. Token, UUID, клиенты и конфигурации не менялись. Ручной откат revision не выполнялся, чтобы не нарушить монотонность версии и согласованность кэша.

## Обязательные сценарии

Обозначения: **пройден** — подтверждён требуемый результат; **частично** — контракт проверен тестом, но отсутствует требуемая физическая среда; **не проверен** — нет достаточного подтверждения.

| № | Сценарий | Результат | Способ проверки |
|---:|---|---|---|
| 1 | Сервер online | **Пройден** | Реальная token-авторизация, `testConnection()`, получение одного inbound; локальный status=`online`. |
| 2 | Повторная синхронизация без дублей | **Пройден** | Две реальные синхронизации: 1 inbound до/после, 0 дублей, `config_changed=false`, revision без изменения. |
| 3 | VLESS TCP Reality + Vision | **Пройден** | Реальный временный клиент создан в remote inbound; Flow `xtls-rprx-vision` подтверждён повторным чтением и subscription URI. |
| 4 | VLESS XHTTP без Vision | **Частично** | Unit и integration прошли, несовместимый Flow блокируется; реального XHTTP inbound в текущем 3x-ui нет. |
| 5 | Тариф с несколькими серверами | **Частично** | Integration fixture подтвердил несколько server+inbound связок и уникальность; физических V2-серверов сейчас 1. |
| 6 | Мультисерверная подписка | **Частично** | Integration fixture подтвердил local-first provisioning, частичный успех и несколько configs; реальный мультисерверный цикл невозможен при одном сервере. |
| 7 | Реальное VPN-подключение | **Не проверен** | VLESS-порт доступен по TCP, URI валиден; xray/sing-box/v2ray и реальное VPN-приложение в среде отсутствуют. |
| 8 | Изменение срока | **Пройден** | Реальный update клиента, повторное чтение и точное совпадение expiryTime. |
| 9 | Изменение лимита | **Пройден** | Реальный update totalGB до тестового значения и повторное подтверждение. |
| 10 | Изменение Flow | **Пройден** | Реальное удаление Flow и возврат Vision; оба состояния подтверждены в 3x-ui и той же subscription-ссылке. |
| 11 | Отключение | **Пройден** | Реальный клиент получил enable=false; endpoint стал возвращать `403`. |
| 12 | Включение | **Пройден** | Реальный клиент получил enable=true; endpoint снова вернул `200`. |
| 13 | UUID не меняется | **Пройден** | Сравнение локальной и удалённой identity до/после всех edits. Полное значение в отчёт не выводилось. |
| 14 | Клиент не пересоздаётся | **Пройден** | Число клиентов inbound после edits увеличено ровно на один относительно baseline и вернулось к baseline после удаления. |
| 15 | Трафик не сбрасывается | **Пройден** | Реальный payload всегда содержит reset=0; счётчики не уменьшились. Integration дополнительно проверил ненулевой monotonic counter. |
| 16 | Subscription token не меняется | **Пройден** | Hash-safe сравнение token до/после edits; старый token блокирован после удаления. |
| 17 | ETag меняется после config edit | **Пройден** | Реальный revision вырос, ETag той же ссылки изменился; повторный If-None-Match дал `304`. |
| 18 | Dropdown на ПК | **Пройден** | In-app browser, общий table component, floating portal, координаты и движение при scroll измерены. |
| 19 | Dropdown на iPhone Safari | **Частично** | Viewport 390×844: portal, позиция, границы viewport и scroll пройдены. Использовался Chromium runtime, не Safari/WebKit на устройстве. |
| 20 | «Мой VPN» | **Пройден** | Реальный браузерный вход fixture-пользователя: пункт меню, тариф, даты, трафик, сервер, флаг, QR, инструкции и copy URL присутствуют. |
| 21 | Ownership | **Пройден** | Чужой subscription ID под учётной записью fixture-пользователя вернул страницу `404`; repository integration также прошёл. |
| 22 | Настройки production | **Частично** | Штатная DB-запись, read-after-write, 26/26 keys, checkbox=false, cache invalidation и отсутствие лишнего revision прошли. Проверено на MAMP/macOS, не на deployed Linux production. |
| 23 | Полное удаление | **Пройден** | Реальный клиент удалён, отсутствие подтверждено, локальные nodes/subscription удалены, пользователь сохранён, старый token=`404`. |
| 24 | Повтор удаления после ошибки | **Пройден** | Integration смоделировал недоступный сервер, delete_failed, восстановление и retry; повтор после уже успешного удаления идемпотентен. |
| 25 | Уведомления без дублей | **Пройден** | Повторные jobs для 3 дней, дня окончания, 80%, 100%, provisioned и critical не создали повторные сообщения. |
| 26 | Отсутствие секретов в логах | **Пройден** | Фактические token/UUID/серверные секреты искались без вывода значений в app, PHP и Apache logs; совпадений 0, Authorization header не найден. |

## HTTP subscription endpoint

Фактическая HTTP-проверка маршрута `GET /vpn-v2/subscription/{token}`:

- status `200` для активной подписки;
- `Content-Type: text/plain; charset=utf-8`;
- `Cache-Control: private, no-cache, must-revalidate`;
- присутствуют ETag и Last-Modified;
- тело является base64 payload с VLESS URI;
- If-None-Match возвращает `304`;
- недействительный token возвращает `404`.

Полные UUID, token и конфигурации в диагностический вывод и этот документ не включались.

## Состояние БД и регистрации

- 4/4 миграции записаны в `plugin_migrations`.
- 8 таблиц `vpn_v2_*` присутствуют.
- Проверены unique indexes inbound, plan node, subscription token и notification dedupe.
- Проверены 17 foreign keys.
- Зарегистрировано 8 permissions.
- Зарегистрировано 5 jobs.
- Пункт admin menu — `VPN V2`, icon — `ci-server`.
- В исходниках V2 не найдено внешних CDN.
- V1 и V2 используют разные admin/profile/subscription routes.

## Изменённые файлы этапа 13

Исправления продукта:

- `app/Views/themes/default/admin/partials/responsive_table_cards.php`
- `plugins/vpn-manager-v2/src/DTO/InboundSyncResult.php`
- `plugins/vpn-manager-v2/src/Repositories/InboundRepository.php`
- `plugins/vpn-manager-v2/src/Services/InboundSyncService.php`

Приёмочные проверки и отчёт:

- `plugins/vpn-manager-v2/tests/stage13_unit.php`
- `plugins/vpn-manager-v2/tests/stage13_runtime.php`
- `plugins/vpn-manager-v2/tests/stage13_real_acceptance.php`
- `docs/vpn-manager-v2-acceptance-report.md`

Файлы старого `plugins/vpn-manager` на этапе 13 не менялись.

## Непроверенные пункты и причины

1. Реальный VPN-туннель: в окружении нет VPN-клиента, а запуск на пользовательском устройстве требует отдельной ручной приёмки.
2. Импорт/refresh в VPN-приложении: нет подключённого приложения или устройства.
3. XHTTP на реальном сервере: удалённая панель возвращает только один TCP Reality inbound.
4. Реальный multi-server: настроен только один V2 server.
5. Физический iPhone Safari: выполнена responsive-проверка, но не WebKit/device test.
6. Linux production: текущая среда macOS/MAMP. Корректность имён файлов проверена runtime-загрузкой, но production filesystem/reverse proxy не доступны.
7. Одновременная активация V1 и V2: маршруты и namespaces статически не конфликтуют, но V1 локально inactive и его статус не менялся по условию задачи.

## Известные ограничения и тестовые артефакты

- Публичная CMS URL в локальной среде использует localhost/127.0.0.1. Телефон не сможет открыть такую ссылку без публичного домена/LAN URL; кабинет корректно показывает предупреждение.
- До этапа 13 в БД уже оставались артефакты раннего stage-12 прогона: 18 `traffic.sync_failed` events, 2 строки V2 notification outbox и 12 CMS notifications. Их количество после этапа 13 не выросло. Они связаны с существующими подписками, поэтому не удалялись без отдельного явного разрешения на очистку данных.
- Исторические `stage4_runtime.php`—`stage11_runtime.php` жёстко проверяют версию соответствующего старого этапа и закономерно не подходят для текущей версии. Актуальные runtime gates — stage 12 и stage 13.
- Реальный acceptance-тест использовал единственный настроенный сервер и не отправлял profile/email/push уведомление временному пользователю.

## Команды проверки

```bash
find plugins/vpn-manager-v2 -name '*.php' -print0 \
  | xargs -0 -n1 /Applications/MAMP/bin/php/php8.2.0/bin/php -l

for f in plugins/vpn-manager-v2/tests/stage*_unit.php; do
  /Applications/MAMP/bin/php/php8.2.0/bin/php -d session.save_path=/tmp "$f" || exit 1
done

for f in \
  plugins/vpn-manager-v2/tests/stage4_integration.php \
  plugins/vpn-manager-v2/tests/stage5_integration.php \
  plugins/vpn-manager-v2/tests/stage6_integration.php \
  plugins/vpn-manager-v2/tests/stage7_integration.php \
  plugins/vpn-manager-v2/tests/stage8_integration.php \
  plugins/vpn-manager-v2/tests/stage10_integration.php \
  plugins/vpn-manager-v2/tests/stage11_integration.php \
  plugins/vpn-manager-v2/tests/stage12_integration.php; do
  /Applications/MAMP/bin/php/php8.2.0/bin/php -d session.save_path=/tmp "$f" || exit 1
done

/Applications/MAMP/bin/php/php8.2.0/bin/php -d session.save_path=/tmp \
  plugins/vpn-manager-v2/tests/stage12_runtime.php

/Applications/MAMP/bin/php/php8.2.0/bin/php -d session.save_path=/tmp \
  plugins/vpn-manager-v2/tests/stage13_runtime.php

/Applications/MAMP/bin/php/php8.2.0/bin/php -d session.save_path=/tmp \
  plugins/vpn-manager-v2/tests/stage13_real_acceptance.php

git diff --check
```

## Результаты тестов

- PHP syntax: пройдено для всех PHP-файлов VPN Manager V2.
- Unit stages 3–8, 10–13: пройдены.
- Integration stages 4–8, 10–12: пройдены, fixtures очищены.
- Runtime stages 12 и 13: пройдены.
- Реальный stage-13 provisioning/edit/delete: пройден; remote client count восстановлен.
- Browser: «Мой VPN», ownership, copy URL, QR markup, desktop/mobile dropdown — пройдены в доступной среде.
- `git diff --check`: пройден.

## Условия готовности к миграции

Перед миграцией старых данных необходимо:

1. Импортировать текущую V2 subscription-ссылку в целевое VPN-приложение.
2. Установить реальный VPN-туннель и проверить внешний IP/DNS/доступность.
3. Добавить и проверить реальный XHTTP inbound, если XHTTP входит в production scope.
4. Проверить реальную подписку минимум на двух физических 3x-ui серверах.
5. Проверить dropdown и copy/QR на физическом iPhone Safari.
6. Выполнить smoke test на Linux production/staging с публичным HTTPS URL.
7. Отдельно решить, удалять ли известные stage-12 notification/event artifacts.

До закрытия этих пунктов миграцию старых VPN-данных начинать не следует.
