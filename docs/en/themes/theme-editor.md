# Theme editor

Status: **Beta**. The theme editor is the built-in tool for safely editing theme
files during development. Open it in the administration panel:
**Appearance -> Theme editor**.

The editor does not change the Theme API. It is sandboxed to the selected theme
and cannot access FIREBALL CMS core files, other themes, or external paths.

## Features

- open files from the theme tree;
- create files and folders inside allowed directories;
- rename and delete items;
- save text files;
- preview and replace images from `assets/images`;
- view backup history;
- restore any saved file version;
- warn when editing the system `default` theme;
- create a copy of the system theme before editing.

## Editor surface

The current Beta uses a regular `<textarea class="theme-editor-code">`.
The JavaScript is routed through an editor adapter, so Monaco Editor or
CodeMirror can be introduced later without rewriting file opening, saving,
validation, or restore logic.

## Files and folders

Editable text formats: PHP, HTML, CSS, JavaScript, JSON, Markdown, TXT, and SVG.
Existing images in `assets/images` can be previewed and replaced with a file
using the same extension.

New files may use only `.php`, `.css`, `.js`, `.json`, `.md`, or `.txt`.
PHP files are allowed only in `templates` and `partials`. Folders can be created
inside `templates`, `partials`, and `assets`.

Size limits:

- text files: up to 1 MB;
- images: up to 5 MB.

## Validation before saving

Before writing a file, CMS validates:

- PHP with `php -l`;
- syntax errors block saving and show the line plus error description;
- JSON must be valid;
- `theme.json` must contain `name`, `slug`, `version`, `author`,
  `description`, and `preview`;
- `theme.json` slug must match the theme directory;
- SVG is checked for unsafe elements and handlers.

## Backups and restore

Before every save, delete, and restore operation, CMS creates a backup in
`storage/theme-backups`. Each version stores:

- date;
- user;
- file path;
- size.

The editor keeps up to 20 latest versions per file. Restore is available from
the **Backups** panel. The current file state is backed up before restoration.

## Security

The editor is sandboxed inside the current theme:

- existing paths are resolved with `realpath()`;
- `../`, absolute paths, and hidden segments are rejected;
- symbolic links are rejected;
- CMS core files are inaccessible;
- other themes are inaccessible;
- `.env`, `.htaccess`, `.user.ini`, Composer/NPM manifests, SQL, PHAR,
  executable, and shell files are forbidden;
- restore operations verify backup metadata against the current theme and file.

All actions and errors are written to `storage/logs/theme-editor.log`: open,
save, create, delete, rename, restore, syntax errors, and operation errors.

## Default system theme

When editing `default`, CMS displays a warning and offers **Create theme copy**.
Use a copy unless the change is intentionally part of the system template.

## Beta limitations

- the editor is not a full IDE;
- Theme API autocompletion is not available yet;
- visual diffs between versions are not available yet;
- child themes are not available yet. This stage prepares the foundation for
  the next development phase: **Child Themes**.
