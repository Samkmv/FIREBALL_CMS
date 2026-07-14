# VPN Manager V2

This plugin is isolated from `vpn-manager` by its slug, namespace, routes, translations, permission keys, migration journal entry, and `vpn_v2_*` tables.

Stage 1 contains the CMS plugin lifecycle, the admin overview, the schema status reader, the permission catalog, and the initial immutable migration.

Stage 2 adds only server administration and connection checks. Controllers call the server services; only `ThreeXuiClient` performs HTTP requests. Public repository queries never select encrypted secrets, while `ServerSecretService` is the sole bridge between encrypted storage and an in-memory client configuration. Token authentication sends the two headers supported by the existing integration. Password authentication uses a mode-0600 temporary cookie jar after `/login`.

Subscription workflows, QR generation, profile integration, notifications, and V1 data migration remain intentionally out of scope.

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

Database schema changes must be delivered only as new ordered SQL files in `migrations/`. Runtime requests and plugin lifecycle methods must not execute DDL.
