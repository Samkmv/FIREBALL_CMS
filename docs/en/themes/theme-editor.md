# Theme editor

Open **Appearance → Theme editor** in the administration panel. The editor is
restricted to the selected theme and cannot access FIREBALL CMS core files.

## Files and folders

Open files from the tree or create items inside `templates`, `partials`,
`assets/css`, `assets/js`, and `assets/images`. PHP files are allowed only in
`templates` and `partials`.

Text files are limited to 1 MB and images to 5 MB. Images have a preview and can
be replaced with another image using the same extension.

## Validation and backups

PHP is linted before saving. JSON must be valid, and `theme.json` must contain
`name`, `slug`, `version`, `author`, `description`, and `preview`.

Every save, delete, and restore operation creates a backup in
`storage/theme-backups`. The editor keeps the latest 20 versions per file.

## Security

All existing paths are resolved with `realpath()`. Absolute paths, traversal,
hidden files, symbolic links, forbidden extensions, other themes, and core
files are rejected. Actions are logged in `storage/logs/theme-editor.log`.

The default theme displays a warning and offers a **Create theme copy** action.
`theme.json`, `templates/layout.php`, and system root directories cannot be
renamed or deleted.
