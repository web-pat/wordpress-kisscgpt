# Qualitätsprüfung Version 1.0.0

## Dauerhafte Hintergrundjobs: Prüfung am 02.11.2026

- WordPress 6.7, PHP 8.1 CLI und SQLite Database Integration 3.1.2 in einer wegwerfbaren Container-Umgebung.
- PHP-Syntax aller Plugin- und Testdateien sowie PHPCS: bestanden, keine Fehler oder Warnungen.
- Erweiterte Integrationstests bestanden: nicht automatisch geladene Joboptionen und Statusverweise, Cache-Leerung, fehlgeschlagene und veraltet gelesene Claims, Fehler beim Planen der Bereinigung, Ablauf und atomare Bereinigung ohne Beeinträchtigung neuerer Jobs, konkurrierende Übernahme bestehender transienter Jobs sowie Deinstallation unter Erhalt fremder Optionen, Beiträge und Metadaten.
- Dieselben Tests auch mit simuliertem externen Objektcache-Modus bestanden. Kein echter Redis-/Memcached-Server getestet.
- Keine echten OpenAI-Aufrufe oder Kosten. Nur auf einer wegwerfbaren Installation ausführen: die Tests leeren den Objektcache.

## Reparaturen: Prüfung am 01.11.2026

Wegwerfbare Container-Umgebung mit WordPress 6.7, PHP 8.1 CLI und SQLite Database Integration 3.1.2:

- PHP-Syntax aller Plugin- und Testdateien: bestanden.
- PHPCS gemäß `phpcs.xml.dist`: keine Fehler oder Warnungen.
- Erweiterte `integration.php`: bestanden; einschließlich atomarer Leases mit simuliertem konkurrierenden Zugriff, Scheduling-/Speicherfehlern, doppelter Cron-Zustellung, Berechtigungsprüfung, Benutzerkontext und Exception-Behandlung.
- Authentifizierter HTTP-Formularablauf bei deaktiviertem automatischen Cron: POST/Redirect zur Statusseite in ca. 0,05 Sekunden; anschließend Verarbeitung über den registrierten Cron-Hook, Entwurf mit Bearbeitungslink und abgelehnter POST-Wiederholung.
- Frontend mit aktivem Plugin: HTTP 200 ohne kritischen Fehler.
- Isolierter Bootstrap-Test unter PHP 7.4: inkompatible Implementierungsdateien werden nicht geladen; PHP-Hinweis wird registriert. Ein kompletter WordPress-Downgrade-Test wurde nicht bestätigt, da der SQLite-Testtreiber unter diesem Container keine Verbindung aufbauen konnte.
- FAQ-Escaping: Die vorherige Review-Vermutung war ein Fehlalarm; WordPress erhält gültige Entities. Ein Regressionstest bestätigt einfache Entity-Kodierung ohne ausführbare Shortcodes.
- Keine echten OpenAI-Aufrufe oder Kosten. MySQL/MariaDB, Multisite, persistenter Objektcache und produktionsspezifische Worker-Timeouts bleiben separat zu prüfen.

## Historischer Prüfstand

Die folgenden Ergebnisse beziehen sich auf den ursprünglichen Stand vom 27.10.2026, nicht auf die späteren Reparaturen. Die ursprüngliche Zwei-Options-Sperre wurde durch atomare Datenbank-Leases ersetzt.

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
