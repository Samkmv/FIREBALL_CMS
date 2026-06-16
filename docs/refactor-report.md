# Refactor report

## Changed files

- `.htaccess` - reduced Apache upload limits to 50 MB defaults and blocked direct access to `tmp`, `storage`, `database`, and `config`.
- `.gitignore` - ignores `tmp/*`, `storage/backups/*`, and `public/uploads/*` while preserving sentinel/access-control files.
- `public/uploads/.htaccess` - blocks executable upload extensions plus `sql`, `bak`, and `env`.
- `tmp/.htaccess`, `config/.htaccess`, `database/.htaccess` - deny direct web access inside sensitive directories.
- `config/config.php` - added `UPLOAD_SETTINGS.max_file_size` with a 50 MB default.
- `core/Cache.php` - added `LOCK_EX`, TTL fallback, corrupt cache deletion, expired cache cleanup, and `clear(): int`.
- `core/File.php`, `app/Models/FileManager.php`, `app/Controllers/ChatController.php` - switched upload checks to configurable `UploadSettings`.
- `app/Views/themes/default/admin/file_manager_browser.php` - displays the configured upload limit.
- `app/Languages/*` upload strings - removed hard-coded `200 MB` wording.
- `app/Services/DatabaseMaintenanceService.php` - kept as facade and delegated backup/log/cache cleanup responsibilities.
- `core/ThemeManager.php` - kept as facade and delegated theme asset resolution.
- `app/Models/Post.php` - delegated public post cache version/key handling.
- `docs/audit-unused-files.md` - added non-destructive cleanup audit.

## Created classes

- `App\Services\UploadSettings`
- `App\Services\PostPublicCache`
- `App\Services\Maintenance\DatabaseBackupService`
- `App\Services\Maintenance\MaintenanceLogService`
- `App\Services\Maintenance\CacheCleanupService`
- `App\Services\Maintenance\DatabaseResetService`
- `App\Services\Themes\ThemeAssets`

## Split classes

- `DatabaseMaintenanceService` now delegates:
  - database backup to `DatabaseBackupService`;
  - maintenance log table/read/write/clear to `MaintenanceLogService`;
  - cache/temp/log cleanup to `CacheCleanupService`.
- `ThemeManager` now delegates asset URL/path/preview URL handling to `ThemeAssets`.
- `Post` now delegates public cache version/key invalidation to `PostPublicCache`.

## Risks reduced

- Direct web access to sensitive folders is blocked at root and directory level.
- Upload execution risk is reduced in `public/uploads`.
- Default upload limit is reduced from 200 MB to 50 MB and made configurable.
- Cache writes are atomic with `LOCK_EX`.
- Corrupted and expired cache payloads are cleaned up instead of reused.
- CMS maintenance cache clearing now uses the central cache facade.
- Runtime directories are ignored more consistently in git.

## Deferred work

- Full `ThemeManager` split into repository/validator/importer/exporter/renderer should be done in a separate pass with integration tests, because the class mixes filesystem safety, ZIP import/export, scaffold generation, rendering, and theme context.
- Full `DatabaseResetService` extraction is intentionally deferred; reset logic remains in the facade to avoid changing critical destructive behavior.
- `Post` public SQL query extraction is deferred; current query methods are tightly coupled to schema self-healing and normalization.
- `tmp/error.log` is tracked; consider untracking in a dedicated cleanup change.
- Duplicate migration `20260602_create_analytics_visits.sql` exists in both `config/migrations` and `database/migrations`; consolidate only after confirming installer/updater lookup rules.
