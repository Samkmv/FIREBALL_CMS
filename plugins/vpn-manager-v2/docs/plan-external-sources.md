# External sources on VPN plans (1.5.9)

In **VPN → Plans → Edit**, use **External plan sources** to add a subscription URL or a direct VLESS/VMess/Trojan/Shadowsocks URI. Save a new plan first; the existing plan topology/provisioning rules are unchanged.

- This extends the subscription source mechanism, rather than introducing a second importer. Credentials and confirmed snapshots remain encrypted; administration shows masked previews only.
- Existing and future subscriptions inherit their current plan's enabled sources at configuration-read time. Sources are not copied into every subscriber, and a plan change stops inheritance from the previous plan.
- Personal subscription sources remain separate and retain their existing controls. The subscription detail page shows plan sources read-only, with a link to their owning plan.
- Each plan source supports refresh, enable/disable, detach and ordering. Existing background source refresh also covers plan URLs. On refresh failure, the existing last-confirmed-snapshot policy remains in effect.
- Final configuration assembly preserves first-party precedence and technical deduplication. Inactive subscription access checks are unchanged. Each mutation invalidates revisions/caches for plan subscribers and dependent parent subscriptions.
- External credentials are **shared by subscribers of this plan**. The provider, not FIREBALL, controls their actual validity, traffic and limits. Removing a source from FIREBALL does not revoke credentials already issued by the external provider.

Migration `017_add_plan_external_sources.sql` adds nullable plan ownership without deleting existing subscription source rows. Separate uniqueness and ordering indexes keep plan IDs and subscription IDs in different scopes. The standard plugin update applies migrations; no manual SQL is needed.

Offline tests: `tests/plan_external_sources_unit.php` (actual services/cipher/in-memory SQL) and `tests/plan_external_sources_browser.cjs` (actual CMS templates, intercepted browser requests). No live site, database or external VPN panel is used.
