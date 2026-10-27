# Direct OpenAI Article Generator

Installierbares WordPress-Plugin zur Artikelgenerierung über die **direkte OpenAI API**. Ein Administrator beschreibt einen Artikel im Backend; das Plugin speichert die geprüfte Antwort als WordPress-Beitrag. Standardstatus ist **Entwurf**. Es gibt keinen Vermittlungsdienst, keine Registrierung beim Plugin-Autor und keine Laufzeit-Abhängigkeit von Composer oder einem JavaScript-Framework.

## Funktionen in Version 1.0.0

- Einstellungen unter **Einstellungen → OpenAI Article Generator**.
- Eigenes Backend-Menü **Article Generator** (übersetzbar als „Artikelgenerator“).
- Thema, Zielgruppe, Sprache, ungefähre Wortzahl, Tonalität, zusätzliche Anweisungen, Keywords und Gliederung.
- Optionale FAQ, SEO-Titel und Meta Description.
- Frei editierbarer Modellname und optionale Temperature.
- Status: Entwurf (`draft`), ausstehend (`pending`), privat (`private`). Keine Veröffentlichungsoption.
- Strukturiertes JSON, serverseitige Validierung, HTML-Bereinigung und Speicherung mit `wp_insert_post()`.
- SEO-Daten ohne Abhängigkeit von einem SEO-Plugin.
- Klassische Formularverarbeitung; JavaScript deaktiviert lediglich den Senden-Button und zeigt Fortschritt an.
- Eingaben bleiben bei behandelbaren Fehlern im Formular erhalten. Erfolgreiche Requests verwenden POST/Redirect/GET.

## Voraussetzungen

- PHP **8.1+** und WordPress **6.7+**; für den Einsatz die aktuelle stabile WordPress-Version verwenden.
- Übliche WordPress-Datenbank und funktionierende WordPress HTTP API mit HTTPS-Verbindung zu `api.openai.com:443`.
- OpenAI-API-Konto, Projekt-Key, ausreichendes API-Guthaben bzw. Billing und Modellzugriff. Ein ChatGPT-Abonnement allein enthält kein API-Guthaben.
- Modell mit **Chat Completions** und **Structured Outputs / JSON Schema**. Nicht jeder OpenAI-Modellname unterstützt diese Schnittstelle.
- Administrator mit `manage_options`, `edit_posts` und der `create_posts`-Capability des Post-Typs. Für private Beiträge zusätzlich `publish_posts`.
- Webserver/PHP-FPM/Proxy sollten Requests von mindestens 120 Sekunden zulassen. Das OpenAI-Timeout beträgt 90 Sekunden.

## Installation

1. Den Ordner `direct-openai-article-generator` nach `wp-content/plugins/` kopieren oder das Release-ZIP über **Plugins → Installieren → Plugin hochladen** installieren.
2. Das Plugin aktivieren.
3. Unter **Einstellungen → OpenAI Article Generator** den Key und die Standardwerte einrichten.
4. **Article Generator** öffnen und einen Testartikel als Entwurf generieren.

Für die Installation sind `vendor/`, Node.js und Composer nicht erforderlich. Bei Multisite werden Einstellungen je Website gespeichert; eine Netzwerkkonfiguration gibt es nicht.

## OpenAI API Key einrichten

1. Im eigenen Konto auf [platform.openai.com](https://platform.openai.com/) ein Projekt und einen API-Key anlegen.
2. Billing, Budgetlimits und Zugriff auf das gewünschte Modell dort konfigurieren.
3. Den Key in das leere Passwortfeld der Plugin-Einstellungen eingeben und speichern.

Das Feld wird **niemals mit dem gespeicherten Key befüllt**. Leer lassen erhält den bisherigen Key; „Delete saved API key“ entfernt ihn. Das Plugin zeigt nur an, ob ein Key vorhanden ist. Es gibt keinen automatischen Verbindungstest beim Öffnen oder Speichern der Einstellungen.

### Alternative: `wp-config.php`

Vor der Zeile „That's all, stop editing!“ eintragen:

```php
define( 'DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY', 'YOUR_OPENAI_API_KEY' );
```

Die definierte Konstante hat immer Vorrang, auch wenn ihr Wert leer oder ungültig ist. Das Backend zeigt dann nur den Konfigurationshinweis und kein Key-Eingabefeld. Ein alter Datenbank-Key wird beim nächsten Speichern der Einstellungen entfernt. Die Konstante selbst wird bei einer Deinstallation nicht verändert.

Ohne Konstante liegt der Key in einer **nicht automatisch geladenen WordPress-Option**. Er ist dort nicht verschlüsselt; Datenbankzugriff und Backups entsprechend schützen. Eine Verschlüsselung mit einem Schlüssel in derselben Datenbank würde keinen sinnvollen zusätzlichen Schutz schaffen. `wp-config.php` bzw. eine dort ausgewertete Server-Umgebungsvariable ist für verwaltete Installationen zu bevorzugen.

## Konfiguration und Nutzung

Der voreingestellte Modellname ist `gpt-4.1-mini`. Er ist ein editierbarer Startwert, keine fest eingebaute Modellbeschränkung. Modellverfügbarkeit und Preise können sich ändern. Ein Responses-only-Modell ist mit diesem Client nicht verwendbar.

Standard-System-Prompt, Sprache und Artikelstatus lassen sich in den Einstellungen ändern. Im Generator:

1. Thema sowie gewünschte redaktionelle Vorgaben eingeben.
2. Eine ungefähre Länge von **200–4000 Wörtern** wählen.
3. FAQ, Meta Description und SEO-Titel nach Bedarf aktivieren.
4. Modell und Status prüfen. **Temperature standardmäßig leer lassen**; dann wird kein solcher Parameter übertragen. Bei unterstützten Modellen sind Werte von 0 bis 2 möglich.
5. „Generate article“ wählen und die Seite geöffnet lassen.
6. Den erzeugten Beitrag über **Edit draft / Entwurf bearbeiten** öffnen. Bei anderen Status heißt der Link „Edit article“.
7. Fakten, Quellen, Links und Formulierungen redaktionell prüfen und erst anschließend gegebenenfalls im WordPress-Editor veröffentlichen.

Die API erhält ein striktes Schema mit `title`, `content_html`, `excerpt`, `seo_title`, `meta_description`, `keywords` und `faq`. FAQ-Antworten werden als Text mit Fragen als Überschriften an den Artikel angehängt. Die Wortzahl bleibt ein ungefähres Ziel. Der Client begrenzt die Ausgabe auf 12.000 Completion-Tokens und die HTTP-Antwort auf 1 MiB. Unvollständige, verweigerte oder fehlerhaft strukturierte Antworten erzeugen keinen Beitrag.

### Fehler und Doppelübermittlungen

- Fehlertexte unterscheiden Key-/Berechtigungsprobleme, Quota/Rate Limits, Verbindungsprobleme, Timeouts, inkompatible Requests und ungültige Antworten.
- Upstream-Fehlertexte werden nie an den Benutzer weitergereicht.
- Es gibt **keine automatischen Retries**, um unbeabsichtigte Mehrkosten zu vermeiden.
- Einmal-Token gelten 30 Minuten; eine erfolgreiche oder an die API weitergereichte Übermittlung verbraucht das Formular-Token. Ein neuer Versuch erhält ein neues Token.
- Eine zusätzliche Sperre serialisiert Generierungen pro Website. Verwaiste Sperren laufen gewöhnlich nach fünf Minuten aus. Nach einem harten Prozessabbruch direkt beim Anlegen der Sperre kann ein Administrator auf einer ruhenden Website `wp option delete doag_generation_lock` und `wp option delete doag_generation_lock_owner` ausführen.
- Nach Browser-/Proxy-Abbruch zuerst die Beitragsliste prüfen. Ein serverseitiger Request kann noch abgeschlossen worden sein; auch ein Timeout kann API-Kosten verursachen.

## SEO-Metadaten

Gespeichert werden:

| Meta-Key | Wert |
|---|---|
| `_doag_seo_title` | String; leer, wenn nicht angefordert |
| `_doag_meta_description` | String; leer, wenn nicht angefordert |
| `_doag_keywords` | Array bereinigter Strings |

Diese Daten werden nicht automatisch in den Seitenkopf geschrieben und nicht in Yoast-/Rank-Math-/SEOPress-Felder gespiegelt. `DOAG\SEO_Adapter` und `DOAG\Post_Meta_Adapter` bilden die kleine Erweiterungsgrenze für spätere Adapter; zusätzlich steht die Abschluss-Action zur Verfügung. Ein eigenes SEO-Frontend oder eine Metadaten-Metabox ist nicht enthalten.

## Architektur

| Modul | Verantwortung |
|---|---|
| Hauptdatei / `Plugin` | Laden und Zusammenstellen der Komponenten |
| `Settings` / `Admin\Settings_Page` | Defaults, Secret-Zugriff, Settings API und Ausgabe |
| `Article_Input` | Begrenzte und bereinigte Formulareingaben, Statusprüfung |
| `Prompt_Builder` | System-/User-Prompt und JSON-Schema |
| `OpenAI_Client` | WordPress HTTP API, Transport und Response-Prüfung |
| `Article_Validator` | Datentypen, Längen, HTML und Textbereinigung |
| `Article_Service` | Berechtigungen, Generierung, Beitragserstellung und Hooks |
| `Post_Meta_Adapter` | Plugin-eigene SEO-Metadaten |
| `Admin\Generator_Page` | Formular, Nonce, Einmal-Token, Sperre und Ergebnisanzeige |

Keine eigenen Datenbanktabellen, keine Hintergrundjobs zur Generierung und keine öffentlichen AJAX-/REST-Endpunkte. Der Namespace ist `DOAG`; die Text Domain lautet `direct-openai-article-generator`.

## Sicherheit

- Einstellungen nur mit `manage_options`, Generierung zusätzlich mit Post-Capabilities; private Beiträge benötigen `publish_posts`.
- Settings-API-Nonce bzw. eigene Formular-Nonce, benutzergebundene Einmal-Token und serverseitige Status-Allowlist.
- Sanitizing, Typ-/Längenprüfung und kontextbezogenes Escaping der Backend-Ausgaben.
- JSON-Validierung vor Speicherung; `wp_kses_post()` plus engere HTML-Allowlist. Nur einfache Artikel-Tags und Links mit HTTP/HTTPS/mailto; kein JavaScript, keine Event-Attribute, Frames, Bilder, Styles oder Formulare.
- Shortcode-Klammern werden als Entities gespeichert, Block-Kommentare entfernt. Filter-Ausgaben werden vor Speicherung nochmals geprüft.
- Fester HTTPS-Endpunkt, TLS-Prüfung und keine HTTP-Weiterleitungen mit Authorization-Header.
- Kein Key in HTML, Skripten oder ausgegebenen Fehlermeldungen; keine Plugin-Logs, auch nicht bei `WP_DEBUG`.
- API-Aufrufe ausschließlich serverseitig. Die Frontend-Seite lädt keinerlei Plugin-Skripte.

Andere installierte Plugins und serverseitige HTTP-Debugger laufen im selben Vertrauensbereich wie WordPress. Sie können technisch auf Optionen, Konstanten und HTTP-Hooks zugreifen; deren Logging unabhängig kontrollieren. Eigene Erweiterungsfilter sind vertrauenswürdiger PHP-Code und können das Request-Verhalten ändern.

## Datenschutz

**Beim Generieren werden alle ausgefüllten redaktionellen Felder und der konfigurierte System-Prompt direkt an OpenAI übertragen.** Zur Authentifizierung wird der Key im Authorization-Header übertragen. OpenAI sieht außerdem technisch bedingt die IP-Adresse des WordPress-Servers. Das Plugin sendet keine Benutzer-E-Mail, keine WordPress-Benutzer-ID und keine vorhandenen Beiträge als zusätzliche Prompt-Daten.

Die generierten Inhalte werden in der WordPress-Datenbank gespeichert. Formularwerte bleiben bei Fehlern nur in der aktuellen Antwort; das Plugin speichert keine Prompt-Historie. Kurzlebige Transients enthalten lediglich Formular-Token/Benutzer-IDs bzw. Ergebnis-Beitrags-IDs.

Das Plugin selbst:

- sammelt keine Telemetrie,
- sendet nichts an den Plugin-Autor,
- lädt keine externen Tracking-Skripte,
- setzt keine eigenen Tracking-Cookies,
- führt keine versteckten externen Requests aus.

`store: false` wird an OpenAI gesendet. Dies ersetzt keine eigene Prüfung der aktuellen OpenAI-Aufbewahrungsregeln, Projektkonfiguration und vertraglichen Bedingungen. Hinweise: [OpenAI API Data Controls](https://platform.openai.com/docs/guides/your-data), [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy/). Diese Links werden vom Plugin nicht automatisch geladen.

## Hooks und Filter

Alle Filter erhalten Werte **ohne API-Key**. `$input` ist die bereinigte Formulareingabe. Änderungen an Modell/Request-Body müssen weiterhin zur verwendeten OpenAI-Schnittstelle passen.

| Hook | Argumente | Zeitpunkt / Rückgabewert |
|---|---|---|
| `doag_system_prompt` | `string $prompt, array $input` | Vollständiger System-Prompt einschließlich Formatvorgaben; String zurückgeben |
| `doag_user_prompt` | `string $prompt, array $input` | User-Prompt; String zurückgeben |
| `doag_model` | `string $model, array $input` | Modell im Request; String zurückgeben |
| `doag_openai_request_body` | `array $body, array $input` | JSON-Body vor HTTP; Array zurückgeben; keine Header |
| `doag_generated_article_data` | `array $article, array $input` | Nach erster Validierung; danach erneute Validierung; Schema beibehalten |
| `doag_article_created` | `int $post_id, array $article, array $input` | Action nach Beitragserstellung und SEO-Speicherung |

Beispiel in einem eigenen Site-Plugin:

```php
add_filter( 'doag_system_prompt', function ( $prompt, $input ) {
    return $prompt . "\nUse short paragraphs and avoid promotional claims.";
}, 10, 2 );

add_action( 'doag_article_created', function ( $post_id, $article, $input ) {
    update_post_meta( $post_id, '_my_editorial_review_required', '1' );
}, 10, 3 );
```

## Deaktivierung und Deinstallation

Deaktivierung erhält alle Daten. **Löschen/Deinstallieren** entfernt die Plugin-Einstellungen inklusive Datenbank-Key sowie Sperroptionen, bei Multisite auf allen Websites. Generierte Beiträge und ihre Post-Metadaten bleiben ausdrücklich erhalten. Die `wp-config.php`-Konstante bleibt ebenfalls bestehen. Kurzlebige Formular-/Ergebnis-Transients laufen nach spätestens 30 Minuten ab und werden durch den normalen WordPress-Transient-Cleanup entfernt; ein dauerhafter Objektcache verwaltet seine Ablaufzeiten selbst.

## Entwicklung und lokale Tests

```bash
composer install
composer lint
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Composer wird ausschließlich für Entwicklungswerkzeuge benötigt. `phpcs.xml.dist` prüft WordPress Coding Standards einschließlich Sicherheit/i18n und PHPCompatibilityWP ab PHP 8.1. Nur die Anforderungen an formale PHPDoc-Kommentare sind ausgenommen; native Typen bleiben maßgeblich. `composer.lock` fixiert die Werkzeugversionen.

### Offline-Integrationstest

Eine **wegwerfbare lokale WordPress-Testinstallation** mit Administrator einrichten und das Plugin aktivieren. Keine API-Key-Konstante definieren. Dann im WordPress-Verzeichnis:

```bash
wp plugin activate direct-openai-article-generator
wp eval-file wp-content/plugins/direct-openai-article-generator/tests/integration.php
```

Der Test verwendet echte WordPress-Funktionen und eine echte Testdatenbank, fängt aber sämtliche HTTP-Aufrufe per `pre_http_request` ab. Er benötigt keinen echten OpenAI-Key und erzeugt keine API-Kosten. Testbeiträge werden im `finally`-Block gelöscht und Einstellungen zurückgesetzt. Bei einem Prozessabbruch können Testdaten zurückbleiben. Die Tests gehören zum Quellrepository und nicht zum Release-ZIP.

Geprüft werden Berechtigungen, verbotener Publish-Status, Key-Ausgabe/-Vorrang, Request-Parameter, HTTP-/JSON-/Transportfehler, alle drei erlaubten Status, Metadaten, FAQ, Backslashes, XSS-/Shortcode-Bereinigung, erneute Filtervalidierung und Insert-Fehler. Es ist kein PHPUnit-Bootstrap oder zusätzliches Testframework erforderlich.

### Manueller Backend-Test

1. Als Administrator Einstellungen speichern; HTML-Quelltext darf den gespeicherten Key nicht enthalten.
2. Als Redakteur/Abonnent Generator und Einstellungsseite aufrufen: kein Zugriff.
3. Formular mit fehlender/falscher Nonce absenden: Ablehnung mit HTTP 403.
4. Verbotenen Status `publish` oder ungültige Länge senden: Ablehnung, Eingaben bleiben erhalten.
5. Mit gültigem Modell einen kurzen Entwurf erzeugen; Edit-Link, Inhalt und Metadaten prüfen.
6. Gleiche POST-Anfrage erneut senden: Einmal-Token wird abgelehnt.
7. Falschen Key, leere Temperature und inkompatibles Modell testen; statische Fehlermeldung ohne Secret prüfen.
8. Plugin löschen: Einstellungen entfernt, Beiträge und SEO-Metadaten erhalten.

Live-API-Tests benötigen den eigenen OpenAI-Key und kosten Geld. Der dokumentierte automatische Test ersetzt weder einen Live-Modelltest noch die Prüfung des eigenen Hostings.

### Übersetzungen

Die englische Oberfläche ist vollständig übersetzbar; eine POT-Vorlage liegt in `languages/`. Eigene PO/MO-Dateien können dort abgelegt werden, etwa `direct-openai-article-generator-de_DE.mo`.

```bash
wp i18n make-pot . languages/direct-openai-article-generator.pot --exclude=vendor,tests
```

### Release-ZIP bauen

Im Plugin-Repository nach einem Commit:

```bash
mkdir -p build
git archive --format=zip --prefix=direct-openai-article-generator/ \
  -o build/direct-openai-article-generator-1.0.0.zip HEAD
```

`.gitattributes` entfernt Entwicklungsdateien aus dem Archiv. Das ZIP enthält einen Plugin-Hauptordner und kann direkt über WordPress hochgeladen werden.

## Roadmap

Noch nicht implementiert:

- Gutenberg Sidebar und direkte Block-Editor-Integration
- Überarbeitung vorhandener Beiträge
- Automatische interne Verlinkung
- Bildgenerierung und Mediathek-Integration
- Geplante Generierung und Batch-Verarbeitung
- Eigene Prompt-Templates und mehrere OpenAI-Konfigurationen
- Yoast-, Rank-Math- und SEOPress-Adapter
- WooCommerce-Produkttexte und Custom Post Types
- REST API und WP-CLI-Generierungsbefehle
- Usage-/Kostenübersicht

## Lizenz

GPL-2.0-or-later. Siehe [LICENSE](LICENSE). Das Plugin enthält keine externen Laufzeitbibliotheken und keine Telemetrie.
