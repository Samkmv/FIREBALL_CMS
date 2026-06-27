# Theme-Editor

Status: **Beta**. Der Theme-Editor ist das integrierte Werkzeug zum sicheren
Bearbeiten von Theme-Dateien. Er ist in der Administration unter
**Design -> Theme-Editor** verfuegbar.

Der Editor aendert die Theme API nicht. Er arbeitet nur im ausgewaehlten Theme
und kann nicht auf den CMS-Kern, andere Themes oder externe Pfade zugreifen.

## Funktionen

- Dateien aus dem Theme-Baum oeffnen;
- Dateien und Ordner in erlaubten Verzeichnissen erstellen;
- Elemente umbenennen und loeschen;
- Textdateien speichern;
- Bilder aus `assets/images` ansehen und ersetzen;
- Sicherungshistorie ansehen;
- jede gespeicherte Version wiederherstellen;
- Warnung beim Bearbeiten des System-Themes `default`;
- Kopie des System-Themes erstellen.

## Arbeitsflaeche

Die Beta nutzt ein normales `<textarea class="theme-editor-code">`.
Die JavaScript-Logik laeuft ueber einen Editor-Adapter, sodass Monaco Editor
oder CodeMirror spaeter ohne Neuschreiben von Oeffnen, Speichern, Validierung
oder Wiederherstellung integriert werden koennen.

## Dateien, Validierung und Backups

Bearbeitbare Textformate: PHP, HTML, CSS, JavaScript, JSON, Markdown, TXT und
SVG. Neue Dateien duerfen nur `.php`, `.css`, `.js`, `.json`, `.md` oder `.txt`
verwenden. PHP ist nur in `templates` und `partials` erlaubt.

Vor dem Speichern wird PHP mit `php -l` geprueft. JSON muss gueltig sein.
`theme.json` benoetigt `name`, `slug`, `version`, `author`, `description` und
`preview`; der Slug muss zum Theme-Verzeichnis passen.

Vor jedem Speichern, Loeschen und Wiederherstellen wird eine Sicherung in
`storage/theme-backups` angelegt. Pro Datei bleiben bis zu 20 Versionen mit
Datum, Benutzer, Pfad und Groesse erhalten.

## Sicherheit

Alle Pfade werden im aktuellen Theme sandboxed. `realpath()` verhindert Zugriff
auf externe Orte. `../`, absolute Pfade, versteckte Segmente, symbolische Links,
andere Themes, CMS-Kerndateien und gefaehrliche Dateitypen sind verboten.
Wiederherstellungen pruefen die Backup-Metadaten gegen Theme und Datei.

Aktionen und Fehler werden in `storage/logs/theme-editor.log` protokolliert.

## Beta-Grenzen

Der Editor ist noch keine vollstaendige IDE. Autovervollstaendigung, visuelle
Diffs und Child Themes folgen in spaeteren Phasen.
