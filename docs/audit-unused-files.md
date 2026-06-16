# Audit: unused and runtime files

No files were deleted during this audit. Items below are candidates or runtime artifacts that need confirmation before removal.

| File | Reason | Where used | Decision |
| --- | --- | --- | --- |
| `tmp/cache/*` | Runtime file cache entries. | Written/read through `FBL\Cache`; cleared by CMS maintenance. | Keep out of git via `.gitignore`; do not delete in refactor. |
| `tmp/cache/rate-limits/*.json` | Runtime rate limiter state. | `core/RateLimiter.php`. | Keep out of git via `.gitignore`; do not delete manually. |
| `tmp/error.log` | Runtime PHP/application log, currently tracked. | `ERROR_LOGS` in `config/config.php`; truncated by maintenance cleanup. | Keep for compatibility now; consider untracking in a separate cleanup commit. |
| `public/uploads/snimok-ekrana-2026-06-15-v-212239.png` | User-uploaded media in writable uploads. | Potentially referenced by content/database; no static reference is enough to prove unused. | Keep; `public/uploads/*` is ignored for future files while `.htaccess` remains tracked. |
| `config/migrations/20260602_create_analytics_visits.sql` | Duplicate content of `database/migrations/20260602_create_analytics_visits.sql`. | Migration/update flow needs confirmation before removing either location. | Keep; candidate for later consolidation after installer/updater audit. |
| `database/migrations/20260602_create_analytics_visits.sql` | Duplicate content of `config/migrations/20260602_create_analytics_visits.sql`. | Database migration source; likely used by install/update tooling. | Keep. |
| `public/assets/default/js/*` and `themes/default/assets/js/*` | Some assets are intentionally mirrored between core default assets and the active default theme. | `app/Views/layouts/default.php` uses `/assets/default`; `themes/default/templates/layout.php` uses `theme_asset()`. | Keep both copies; not safe to deduplicate without asset build changes. |
| `storage/backups/*` | Runtime database backup output. | `DatabaseBackupService` writes SQL backups here. | Keep directory sentinels only in git; ignore generated backups. |
| `database/*.sql` | Install/demo/schema SQL. | Installer and maintenance flows may depend on these files. | Keep. |
