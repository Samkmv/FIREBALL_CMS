# Согласование тарифов и устройства 3x-ui

## Аудит до изменений (28.09.2026)

- `PlanManagerService::update` сравнивает только узлы тарифа; изменение `device_limit`/трафика не ставит согласование в очередь. Дополнительно запуск зависит от checkbox.
- `VpnPlanSubscriptionReconciler` уже умеет preview, повтор, создание отсутствующих узлов, отдельное подтверждённое удаление лишних. Существующие узлы проверяются только по локальному Flow.
- `vpn_v2_reconcile_operations` и `VpnV2ReconcilePlanSubscriptionsJob` уже хранят прогресс и обрабатывают подписки пачками. Новая очередь не требуется.
- Количество подключений определяется `vpn_v2_plan_nodes`; `device_limit` — отдельное поле, но `ClientPayloadFactory` ошибочно передаёт его в `limitIp`.
- Создание использует `SubscriptionProvisioningService`; редактирование/продление — `SubscriptionEditingService`; блокировка и обслуживание — `SubscriptionAutomationService` и сервис зависимостей. Они используют общие payload/verifier/remote sync, но provisioning дублирует read/update/verify.
- HTTP сосредоточен в `ThreeXuiClient`. Modern update получает полный объект перед заменой и нормализует массивы; это поведение необходимо сохранить.
- Маршруты админки защищены auth/admin, операции дополнительно проверяют `Permissions`; CSRF проверяет общий Router до вызова контроллера.
- Истечение срока и бессрочное редактирование уже реализованы. Нужно сохранить access policy, стабильные UUID/password/subId/email, URL и счётчики.

## План файлов

1. `Services/{PlanManagerService,VpnPlanSubscriptionReconciler,RemoteClientSyncService,ClientPayloadFactory,ClientVerifier,SubscriptionProvisioningService,ConfigurationSyncService}.php` — единое ожидаемое состояние и полное согласование.
2. `Repositories/{PlanRepository,PlanReconciliationRepository,SubscriptionRepository,AutomationRepository,ConfigurationSyncRepository}.php`, `DTO/{PlanData,ReconcilePreview}.php`, `Validators/PlanValidator.php`, `migrations/015_*.sql` — отделить прежний IP-лимит, сохранить его при обновлении, расширить результат проверки.
3. `Clients/ThreeXuiClient.php`, новый сервис/контроллер устройств — актуальные API и проверка принадлежности соединения подписке.
4. `Controllers/Admin/PlanController.php`, `views/admin/{plan-form,subscription-detail,subscription-devices}.php`, `routes/admin.php`, `lang/*.php` — существующая карточка согласования и управление устройствами.
5. `Jobs/VpnV2ReconcilePlanSubscriptionsJob.php`, `Plugin.php`, `cron.php` — использовать существующий обработчик очереди автоматически.
6. `tests/*hwid*`, сопутствующие существующие проверки, `plugin.json`, документация — регрессии и описание обновления.

## Проверенный контракт 3x-ui

Источники: [релиз v3.8.5](https://github.com/MHSanaei/3x-ui/releases/tag/v3.8.5), [контроллер клиентов](https://github.com/MHSanaei/3x-ui/blob/v3.8.5/internal/web/controller/client.go), [HWID service](https://github.com/MHSanaei/3x-ui/blob/v3.8.5/internal/web/service/client_hwid.go), [OpenAPI](https://github.com/MHSanaei/3x-ui/blob/v3.8.5/frontend/public/openapi.json).

- `limitHwid` находится в глобальной записи клиента; inbound settings могут его не содержать. Для фактической проверки требуется `GET /panel/api/clients/get/{email}`.
- Устройства: `POST /panel/api/clients/hwids/{email}` (чтение), `DELETE` того же адреса (все), `DELETE .../{id}` (одно). Реестр общий для subId.
- Уменьшение лимита не удаляет устройства: ранее зарегистрированное продолжает проходить проверку, новое отклоняется при заполненном лимите.
- `bulkAdjust` сдвигает срок и трафик, а не задаёт абсолютные значения. Согласование требует абсолютного состояния и подтверждения каждого клиента, поэтому использует read/merge/update/read существующего клиента.
- Нативная HWID-проверка срабатывает при загрузке подписки 3x-ui с заголовком X-HWID. Сам Xray не проверяет физическое устройство при подключении с уже выданным UUID. Ссылка CMS генерирует конфигурации самостоятельно; установка limitHwid сама по себе не превращает её в нативный HWID gateway.

Данные текущих пользователей и реальные панели не используются для разрушительных тестов. Проверки выполняются на изолированных локальных записях с подставным API.
