# VPN Manager V2

Stage 12 adds cron-compatible job entry points, monotonic traffic synchronization, expiration and traffic-limit enforcement, a persistent notification outbox and bounded retries. It does not migrate data from VPN Manager V1.

This plugin is isolated from `vpn-manager` by its slug, namespace, routes, translations, permission keys, migration journal entry, and `vpn_v2_*` tables.

Stage 1 contains the CMS plugin lifecycle, the admin overview, the schema status reader, the permission catalog, and the initial immutable migration.

Stage 2 adds only server administration and connection checks. Controllers call the server services; only `ThreeXuiClient` performs HTTP requests. Public repository queries never select encrypted secrets, while `ServerSecretService` is the sole bridge between encrypted storage and an in-memory client configuration. Token authentication sends the two headers supported by the existing integration. Password authentication uses a mode-0600 temporary cookie jar after `/login`.

At Stage 2, subscription workflows, QR generation, profile integration, notifications, and V1 data migration were intentionally out of scope.

Stage 3 adds read-only inbound synchronization. Remote inbound snapshots are parsed independently, upserted by `(server_id, remote_inbound_id)`, and absent rows are marked `sync_missing` without deletion. Sensitive Reality keys and client lists are not persisted in the snapshot. Security is derived only from `streamSettings`; client Flow compatibility is handled separately by `VpnFlowResolver`. No remote inbound mutation is performed.

Stage 4 adds administrative plan management over the existing `vpn_v2_plans` and `vpn_v2_plan_nodes` schema. A plan owns one or more ordered links to enabled servers and active inbounds. Every write is validated against the current database topology and committed in a transaction. The database unique key `(plan_id, server_id, inbound_id)` is the final duplicate guard.

Flow override has three distinct form states: automatic is stored as `NULL`, explicit no-Flow is stored as an empty string, and a named Flow is normalized and checked by `VpnFlowResolver`. The browser receives only the resolver-derived allowed Flow list for each inbound; the same compatibility check is repeated server-side before storage. Editing plan links deletes and recreates only rows in `vpn_v2_plan_nodes`, never servers or inbounds.

Stage 4 does not create 3x-ui clients, subscriptions, subscription endpoints, or remote mutations.

Stage 5 adds local-first subscription provisioning. The CMS user, active plan, and active plan nodes are validated before a short database transaction creates the subscription and all `creating` nodes. Subscription token, UUIDs, technical client e-mails, optional client sub-IDs, protocol dimensions, and resolved Flow are fixed locally before commit. HTTP starts only after that transaction commits.

Provisioning checks for an existing remote client by UUID or technical e-mail before calling `addClient`. After creation it fetches the inbound again and verifies UUID, e-mail, enabled state, expiry, traffic limit, IP limit, and Flow. A remote client is never considered confirmed from HTTP 200 alone. Failed local rows are retained and may be retried idempotently. Event contexts redact token-, credential-, cookie-, authorization-, and UUID-like fields by key and never contain a client payload.

The `clients/add` request uses the 3x-ui client wrapper `{client, inboundIds}`. Universal numeric fields, including `tgId`, are sent as integers. The payload's `security=auto` is the panel's per-client protocol cipher field; it is never populated from the inbound's TLS/Reality security. The latter remains an independent local node dimension, while Flow is resolved and verified separately.

Stage 5 does not add subscription configuration generation, a public subscription endpoint, QR codes, profile integration, notifications, traffic accounting, client update/delete workflows, or V1 migration.

Stage 6 adds the isolated public endpoint `GET /vpn-v2/subscription/{token}`. Its default response is a base64-encoded newline-separated subscription; `?format=plain` returns the same validated VLESS URIs as plain text. The endpoint rejects unknown, inactive, not-yet-started, and expired subscriptions before reading cached configuration data. It emits `Content-Type`, `Cache-Control: private, no-cache, must-revalidate`, `ETag`, and `Last-Modified`, and supports conditional `304` responses.

`VpnSubscriptionBuilder` reads the subscription's current active nodes joined to the current server and inbound rows. It never reads a stored legacy VLESS URI. Each node is decoded independently and validated before any payload is returned. Reality, TLS, and `none` security branches are mutually exclusive; a missing Flow is omitted from the query entirely. XHTTP path, host, mode, padding, headers, multiplexing, and other bidirectional settings come only from that node's own `stream_settings_json`.

The cache key contains only a SHA-256 token hash plus revision and format. `VpnSubscriptionRevisionService` invalidates the previous revision and increments revision when provisioning activates new nodes, a server changes, or inbound synchronization refreshes transport/security data. Expiration and subscription status are still checked on every request, including cache hits.

The administrative QR is generated locally with the project's bundled BaconQrCode dependency. `QrCodeService` accepts a subscription token and internally constructs only the V2 public subscription URL; it has no arbitrary-value or external-service mode.

Stage 6 does not add profile integration, traffic synchronization, subscription editing, client update/delete, notifications, background jobs, or V1 migration.

Stage 7 adds verified one-way editing for subscriptions and connections. `RemoteClientSyncService` first reads the existing client from its own inbound, verifies UUID and technical e-mail, calculates a field-level diff, calls `updateClient()` only when necessary, reads the inbound again, and verifies the changed fields. The edit path never calls `addClient()`, `deleteClient()`, or a traffic-reset endpoint.

Expiration, traffic limit, and status are propagated to every subscription node as `expiryTime`, `totalGB`, and `enable`. Confirmed nodes are stored locally; failed nodes become `sync_error`, while the requested subscription state is retained with a safe partial-error marker. A config-changing operation performs one revision bump and invalidates the old cache. Internal comments use `vpn_v2_subscriptions.internal_comment`, remain CMS-only, and do not trigger HTTP, revision, or cache invalidation.

Connection editing supports a resolver-compatible Flow and a per-node traffic limit. Explicit pull imports Flow, totalGB, and observed traffic into one local node. Explicit push sends the current local node state to 3x-ui. There is no implicit bidirectional merge, UUID/e-mail/token mutation, client recreation, or traffic reset.

Stage 7 does not add profile integration, notifications, background jobs, client deletion, or V1 migration.

Stage 8 separates suspension from permanent deletion. Suspension keeps the local
subscription, nodes, and public token, sends `enable=false` to every remote client,
reads each inbound again, and stores only the confirmed outcome. A partial remote
failure leaves the subscription suspended and marks the affected nodes as
`sync_error`.

Permanent deletion is an idempotent, two-phase workflow. A short transaction marks
the subscription and nodes as deleting, then every 3x-ui deletion is performed
outside a database transaction against the node's own server and inbound. The
client is matched by UUID, technical e-mail, or sub-ID; a present client must also
pass the strict UUID/e-mail identity check before deletion. A second inbound read
must confirm that no matching identity remains. A client that is already absent is
treated as successfully deleted.

Confirmed nodes remain locally marked as deleted during a partial failure, so a
retry does not call 3x-ui for them again. If any remote deletion fails, the local
subscription and token remain available for recovery with status `delete_failed`.
Only after all nodes are confirmed absent are the revision-scoped subscription
cache and token-scoped QR cache invalidated, the token rotated to an unreachable
random revoked value, and the local nodes and subscription deleted in a final
short transaction. CMS users, plans, servers, and inbounds are never part of that
transaction.

Stage 8 adds no database migration and does not add profile integration,
notifications, background jobs, traffic accounting, or V1 migration.

Stage 10 adds the authenticated customer cabinet at `/profile/vpn-v2`. The
existing `profile_menu` Plugin API registers “My VPN” only when a physical
`vpn_v2_subscriptions` row exists for the current user; no core profile template
is modified. Every selected subscription is queried with both its ID and the
current user ID, so changing the route ID cannot expose another user's data.

The profile repository returns a deliberately limited projection: plan, status,
dates, aggregate traffic, connection count, and display-only server location.
Panel URLs, remote inbound IDs, client identifiers, internal JSON, technical
e-mails, and stored errors never enter the view data. The full token is read only
inside `ProfileVpnService` to build the existing public V2 URL and local QR SVG.
Inactive, future, and expired subscriptions do not expose a copyable link or QR.

The same local plugin asset performs clipboard copying inside the user gesture,
then tries the modern API where the browser permits it. If both automatic paths
are blocked, an iOS-compatible read-only field is revealed, focused, and fully
selected for the system Copy command. Instructions for iPhone/iPad, Android,
Windows, and macOS are stored entirely in the plugin translations. Stage 10 adds
no database migration and does not implement any Stage 11 functionality.

Stage 11 stores persistent VPN V2 settings in the CMS-owned `plugin_settings`
table under the `vpn-manager-v2` slug. `SettingsRepository` uses the existing
JSON value convention and the core unique key `(plugin_slug, setting_key)`, but
does not execute DDL at request time. A save locks existing setting rows with
`SELECT ... FOR UPDATE`, is transactional, and is verified by a read inside the
transaction and by a second fresh read after commit. The
controller emits a success flash only after both comparisons pass.

Branding-related changes (`service_name`, `server_name_template`, and
`global_show_flags`) update only the URI display fragment. Every active
subscription receives a local revision bump and old/new revision cache entries
are invalidated; no 3x-ui call, client recreation, UUID/e-mail/sub-ID mutation,
or traffic reset occurs. The template renderer supports only `{flag}`,
`{service}`, `{country}`, `{country_code}`, `{city}`, `{server}`, and
`{protocol}`. `CountryFlagService` accepts assigned ISO 3166-1 alpha-2 codes and
the flag is never used outside the display name.

Subscription, QR, and settings cache TTLs are clamped to safe ranges. Changing
a cache TTL invalidates the corresponding existing cache entries. Expired
subscription responses can be configured as HTTP 410 or HTTP 404; both modes
return no subscription body. The customer account consumes service branding,
global/per-server flag policy, support details, logo, QR visibility, and its
global enabled switch. Stage 11 adds no database migration and does not
implement Stage 12 functionality.

Stage 12 adds the five cron-compatible job classes documented in
`docs/automation.md`. `TrafficSyncService` reads `up` and `down` for the exact
technical client identifier from 3x-ui and never interprets the remote quota
field as used traffic. Confirmed local node counters are monotonic, and
`vpn_v2_subscriptions.traffic_used_bytes` is recalculated from all non-deleted
nodes. A missing, malformed, or temporarily unavailable remote response records
only a safe diagnostic error and leaves both node and subscription counters
unchanged.

Expiration and traffic-limit checks transition local state first, bump the
configuration revision, invalidate the subscription cache, and then use the
existing verified remote read/update/read path with `reset=0`. Expired and
traffic-exceeded clients are disabled. Partial failures remain recoverable as
`sync_error`; remote requests are never made inside a database transaction.

`vpn_v2_notifications` is a persistent notification outbox. Its unique key
`(subscription_id, notification_type, occurrence_key, channel)` prevents a
second execution from sending the same occurrence twice. Profile and enabled
push delivery use the CMS `NotificationService`; optional email uses the CMS
`MailService`. The retry job handles failed provisioning nodes, failed remote
deletions, failed lifecycle synchronization, and failed notification deliveries
in bounded batches. Stage 12 does not reset traffic and does not migrate V1
data.

Database schema changes must be delivered only as new ordered SQL files in `migrations/`. Runtime requests and plugin lifecycle methods must not execute DDL.
