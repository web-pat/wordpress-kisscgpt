# Qualitätsprüfung Version 1.0.0

Durchgeführt am 27.10.2026 in einer lokalen, wegwerfbaren Installation:

- WordPress 7.1.3 (Download von wordpress.org/latest.zip)
- PHP 8.4.27 CLI und integrierter HTTP-Testserver
- SQLite Database Integration 3.1.1 als lokale Testdatenbank
- WordPress Coding Standards 3.4.1 / PHP_CodeSniffer 3.13.6
- PHPCompatibilityWP mit Zielbereich PHP 8.1+

## Ergebnisse

| Prüfung | Ergebnis |
|---|---|
| Plugin-Aktivierung in WordPress | Bestanden |
| PHP-Syntax aller Plugin- und Testdateien | Bestanden |
| PHPCS gemäß `phpcs.xml.dist` | 0 Fehler, 0 Warnungen |
| `git diff --check` | Bestanden |
| `tests/integration.php` mit simuliertem HTTP | Bestanden |
| Echte Backend-HTTP-Anmeldung und Formularaufruf | Bestanden |
| POST mit falscher Nonce | HTTP 403 |
| POST mit `publish` | Abgelehnt; Eingaben erhalten |
| Gültiger POST → Redirect → Ergebnis | Entwurf mit Bearbeitungslink |
| Wiederholung desselben POST | Abgelehnt |
| Einstellungen im HTML | Gespeicherter Key nicht vorhanden |
| Redakteur auf Generator-/Einstellungsseite | Jeweils HTTP 403 |
| Uninstall-Code auf Testdaten | Einstellungen/Sperren entfernt; Beitrag und SEO-Meta erhalten |

## Eigene Code-Review

- Alle produktiven PHP-Einstiegspunkte sind gegen Direktzugriff geschützt.
- Settings API und Generator verlangen serverseitig Berechtigungen und Nonces.
- Publish ist auch bei manipuliertem POST ausgeschlossen; private Beiträge verlangen `publish_posts`.
- Eingaben werden vor API-Aufruf validiert; strukturierte Ausgabe wird vor und nach Erweiterungsfiltern geprüft.
- HTML wird über `wp_kses_post()` und eine zusätzliche Allowlist bereinigt, Shortcodes und Block-Kommentare werden entschärft.
- Alle UI-Fehler aus API- und Insert-Pfaden sind statisch; keine rohen Fehlerantworten oder Secrets werden ausgegeben.
- Der Key wird nicht in ein HTML-Feld zurückgeschrieben. Konstante hat Vorrang, DB-Option lädt nicht automatisch.
- Der feste HTTPS-Endpunkt akzeptiert keine Redirects. Es gibt weder Retry-Schleifen noch Plugin-Logging.
- Einmal-Token verhindern übliche Replay-Übermittlungen; die zusätzliche Site-Sperre verwendet einen konstanten Optionswert, damit konkurrierende `add_option()`-Aufrufe keinen neuen Lock-Wert überschreiben.
- `wp_slash()` erhält Backslashes beim Speichern von Beitrag und Metadaten.
- Deinstallation löscht keine Beiträge oder Post-Metadaten.

## Noch nicht durch die lokale Prüfung abgedeckt

- Kostenpflichtiger Live-Aufruf mit einem echten OpenAI-Projekt und Modellzugriff.
- Laufzeittest unter PHP 8.1 (Kompatibilität statisch geprüft).
- Separate MySQL-/MariaDB- und Multisite-Laufzeittests.
- Hosting-spezifische PHP-FPM-/Reverse-Proxy-Timeouts und Interaktionen mit fremden Plugins.

Die reproduzierbaren automatischen Tests und manuellen Prüfschritte stehen im Haupt-README. Die temporäre SQLite-Installation, HTTP-Fixtures und Testzugangsdaten sind kein Bestandteil des Plugins.
