# Theme-Editor

Der Editor ist unter **Design → Theme-Editor** erreichbar. Er arbeitet
ausschliesslich innerhalb des ausgewaehlten Themes und hat keinen Zugriff auf
Dateien des CMS-Kerns.

PHP, HTML, CSS, JavaScript, JSON, Markdown und TXT koennen als Text bearbeitet
werden. Vorhandene Bilder in `assets/images` koennen angesehen und ersetzt
werden.

Neue Dateien duerfen nur `.php`, `.css`, `.js`, `.json`, `.md` oder `.txt`
verwenden. Ordner koennen nur in `templates`, `partials` und `assets` erstellt
werden. PHP ist nur in `templates` und `partials` erlaubt. Textdateien sind auf
1 MB, Bilder auf 5 MB begrenzt.

Vor dem Speichern wird PHP syntaktisch geprueft. JSON muss gueltig sein und
`theme.json` benoetigt `name`, `slug`, `version`, `author`, `description` und
`preview`.

Vor Speichern, Loeschen und Wiederherstellen wird eine Sicherung unter
`storage/theme-backups` angelegt. Pro Datei bleiben die letzten 20 Versionen.

`realpath()` begrenzt alle Operationen auf das Theme. Absolute Pfade,
Traversal, versteckte Dateien, symbolische Links und verbotene Dateitypen
werden abgelehnt. Aktionen stehen in `storage/logs/theme-editor.log`.
