# Theme-Editor

Der Editor ist unter **Design → Theme-Editor** erreichbar. Er arbeitet
ausschliesslich innerhalb des ausgewaehlten Themes und hat keinen Zugriff auf
Dateien des CMS-Kerns.

Dateien und Ordner koennen in `templates`, `partials`, `assets/css`,
`assets/js` und `assets/images` erstellt werden. PHP ist nur in `templates`
und `partials` erlaubt. Textdateien sind auf 1 MB, Bilder auf 5 MB begrenzt.

Vor dem Speichern wird PHP syntaktisch geprueft. JSON muss gueltig sein und
`theme.json` benoetigt `name`, `slug`, `version`, `author`, `description` und
`preview`.

Vor Speichern, Loeschen und Wiederherstellen wird eine Sicherung unter
`storage/theme-backups` angelegt. Pro Datei bleiben die letzten 20 Versionen.

`realpath()` begrenzt alle Operationen auf das Theme. Absolute Pfade,
Traversal, versteckte Dateien, symbolische Links und verbotene Dateitypen
werden abgelehnt. Aktionen stehen in `storage/logs/theme-editor.log`.
