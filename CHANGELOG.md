# Changelog

## Unreleased

- Hintergrundjobs und Statusverweise werden in nicht automatisch geladenen Datenbankoptionen gespeichert und überleben Cache-Leerungen. Zeitgesteuerte und lesende Bereinigung entfernt abgelaufene Jobs; bestehende transiente Jobs werden übernommen. Regressionstests prüfen Cache-Verlust, fehlgeschlagene Claims, Bereinigung und Upgrade-Verhalten.
- PHP-Versionsprüfung vor dem Laden der Implementierung; eine PHP-Herabstufung legt WordPress nicht durch inkompatible Plugin-Dateien lahm.
- Atomare Datenbank-Leases ersetzen die bisherige Zwei-Options-Sperre; veraltete Requests können neue Sperren weder überschreiben noch löschen.
- Artikelgenerierung läuft einmalig über WP-Cron, statt den Admin-Request bis zu 90 Sekunden zu blockieren; Statusanzeige mit automatischer oder manueller Aktualisierung.
- Hintergrundjobs prüfen die Berechtigungen des ursprünglichen Benutzers erneut, stellen den vorherigen Benutzerkontext wieder her und zeigen bei Exceptions keine internen Fehlermeldungen an.
- Regressionstests für konkurrierende Leases, doppelte Cron-Zustellung, abgelaufene Jobs, Exceptions und bereits korrektes FAQ-Bracket-Escaping.
- Dokumentation zu Cron-Voraussetzungen und kurzzeitiger Speicherung von Briefings; Deinstallation entfernt auch Cron-Events und neue Leases.

## 1.0.0 — 2026-10-27

- Native Einstellungsseite mit Settings API und optionalem API-Key aus `wp-config.php`.
- Administratives Formular für Thema, Zielgruppe, Sprache, Länge, Tonalität, Keywords und Gliederung.
- Direkte serverseitige Chat-Completions-Aufrufe mit strengem JSON-Schema.
- Einstellbares Modell und optionale Temperature; keine automatische Wiederholung kostenpflichtiger Requests.
- Geprüfte Speicherung als Entwurf, ausstehender oder privater Beitrag.
- Optionale FAQ im Artikel und SEO-Daten in eigenen Post-Meta-Feldern.
- Nonces, Berechtigungsprüfung, Einmal-Formulare, Generierungssperre und restriktive HTML-Bereinigung.
- Erweiterungshooks, SEO-Adapter-Schnittstelle und übersetzbare Oberfläche mit POT-Datei.
- Offline-Integrationstests, PHPCS-Konfiguration und PHP-Kompatibilitätsprüfung.
