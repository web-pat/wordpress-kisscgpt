# Direct OpenAI Article Generator

An installable WordPress plugin that generates articles **directly through the OpenAI API**. An administrator describes an article in the admin area; the plugin saves the validated response as a WordPress post. The default status is **Draft**. There is no intermediary service, no registration with the plugin author, and no runtime dependency on Composer or a JavaScript framework.

## Features in Version 1.0.0

- Settings under **Settings → OpenAI Article Generator**.
- Dedicated **Article Generator** admin menu, with a translatable label.
- Topic, target audience, language, approximate word count, tone, additional instructions, keywords, and outline.
- Optional FAQ, SEO title, and meta description.
- Freely editable model name and optional temperature.
- Statuses: draft (`draft`), pending review (`pending`), private (`private`). No publishing option.
- Structured JSON, server-side validation, HTML sanitization, and persistence using `wp_insert_post()`.
- SEO data without any dependency on an SEO plugin.
- Traditional form submission queues generation through WordPress cron instead of waiting for OpenAI in the admin request.
- JavaScript disables the submit button and refreshes pending job status; a manual refresh link works without JavaScript.
- Form inputs are preserved on recoverable errors. Queued requests use POST/Redirect/GET.

## Requirements

- PHP **8.1+** and WordPress **6.7+**; use the latest stable WordPress version in production.
- A standard WordPress database and a working WordPress HTTP API with HTTPS access to `api.openai.com:443`.
- An OpenAI API account, project key, sufficient API credits or billing, and model access. A ChatGPT subscription alone does not include API credits.
- A model supporting **Chat Completions** and **Structured Outputs / JSON Schema**. Not every OpenAI model supports this interface.
- An administrator with `manage_options`, `edit_posts`, and the post type's `create_posts` capability. Private posts additionally require `publish_posts`.
- Working WordPress cron: either normal loopback-based WP-Cron or an external runner when `DISABLE_WP_CRON` is enabled. Jobs must start within ten minutes.
- The cron worker should allow requests lasting at least 120 seconds. The OpenAI timeout is 90 seconds. Admin page requests do not wait for the API; background workers still use server resources and must fit hosting limits.

## Installation

1. Copy the `direct-openai-article-generator` folder into `wp-content/plugins/`, or install the release ZIP through **Plugins → Add New Plugin → Upload Plugin**.
2. Activate the plugin.
3. Configure the key and defaults under **Settings → OpenAI Article Generator**.
4. Open **Article Generator** and generate a test article as a draft.

Installation does not require `vendor/`, Node.js, or Composer. On Multisite, settings are stored per site; there is no network-wide configuration.

## Setting Up an OpenAI API Key

1. Create a project and an API key in your account at [platform.openai.com](https://platform.openai.com/).
2. Configure billing, budget limits, and access to the desired model there.
3. Enter the key in the empty password field in the plugin settings and save.

The field is **never populated with the saved key**. Leaving it empty preserves the existing key; “Delete saved API key” removes it. The plugin only indicates whether a key is present. Opening or saving the settings does not trigger an automatic connection test.

### Alternative: `wp-config.php`

Add the following before the “That's all, stop editing!” line:

```php
define( 'DIRECT_OPENAI_ARTICLE_GENERATOR_API_KEY', 'YOUR_OPENAI_API_KEY' );
```

The defined constant always takes precedence, even if its value is empty or invalid. The admin area then displays only a configuration notice, with no key input field. Any old database key is removed the next time the settings are saved. Uninstalling the plugin does not modify the constant itself.

Without the constant, the key is stored in a **non-autoloaded WordPress option**. It is not encrypted there; protect database access and backups accordingly. Encryption with a key stored in the same database would not provide meaningful additional protection. For managed installations, prefer `wp-config.php` or a server environment variable read from that file.

## Configuration and Usage

The default model name is `gpt-4.1-mini`. It is an editable starting value, not a hard-coded model restriction. Model availability and pricing may change. Models that only support the Responses API cannot be used with this client.

The default system prompt, language, and article status can be changed in the settings. In the generator:

1. Enter the topic and the desired editorial requirements.
2. Choose an approximate length of **200–4,000 words**.
3. Enable FAQ, meta description, and SEO title as needed.
4. Check the model and status. **Leave temperature empty by default**; this omits the parameter from the request. Supported models accept values from 0 to 2.
5. Select “Generate article”. Generation runs in the background; you may leave the page and return later. While open, the status page refreshes automatically.
6. Open the generated post using **Edit draft**. For other statuses, the link is labeled “Edit article”.
7. Review facts, sources, links, and wording before publishing through the WordPress editor, if appropriate.

### How To: Generate Your First Draft

1. Under **Settings → OpenAI Article Generator**, save your API key and model. Set **Default language** to your desired output language, such as `English`; the initial default is `Deutsch`, independently of the WordPress interface language. Keep **Default article status** set to **Draft** and save changes.
2. Open **Article Generator** and try this editorial brief:

   | Field | Example |
   |---|---|
   | Topic / working title | Starting a small balcony herb garden |
   | Target audience | Beginners with limited outdoor space |
   | Language | English |
   | Target word count | 600 |
   | Tone | Practical, clear and encouraging |
   | Additional instructions | Explain drainage and watering. Avoid unsupported claims and invented quotations. |
   | Keywords | balcony herbs, container gardening, beginner herb garden |
   | Desired outline | Choosing containers; selecting herbs; planting; watering; common mistakes (one heading per line) |

3. Leave **Temperature (optional)** empty. Keep **Generate meta description** and **Generate SEO title** checked; optionally check **Generate FAQ**. Select **Draft**, then **Generate article**. This is a live API request and can incur costs.
4. Wait for the success notice, then select **Edit draft**. Without JavaScript, use **Refresh status**. You can also return to **Article Generator** while logged in as the same administrator.
5. Review the generated title, article, and excerpt in the WordPress editor. Add categories, tags, images, and any required SEO-plugin fields manually, then save or publish through the editor.

The topic is a working title, not a guarantee of the final post title. Keywords and outline are free-text editorial guidance; a comma-separated keyword list and one heading per outline line are useful conventions, not a required parsing format. Do not request raw JSON or a complete HTML document: the plugin supplies its own output-format instructions.

### Defaults and Where To Find Results

- New forms start at **1,000 words**, with a general-reader audience and a clear, factual, approachable tone. FAQ is off; SEO title and meta description are on. Topic, additional instructions, keywords, outline, and temperature start empty.
- Model, language, and status are copied from settings into each new form and can be overridden for that article. Form overrides do not change saved defaults. The system prompt is site-wide and is edited only in settings.
- Only **one generation job per site** can be queued or running at a time, including submissions by other administrators. This is not a batch queue. Keep the plugin active until the job finishes; there is no cancel or retry button.
- Each successful generation creates a **new standard WordPress post**, authored by the submitting administrator, with a title, HTML content, and excerpt. It does not revise an existing post, assign keyword tags, or generate images.
- Find the result under **Posts → All Posts** even if you closed the generator page or its success notice has expired. Draft and pending-review posts await editorial action; private posts use WordPress's normal private visibility and require `publish_posts` to generate.
- The generator shows the current user's latest job, not a permanent history. Status notices and recoverable brief data are retained for **30 minutes from submission**; generated posts remain afterward. Copy any brief you want to reuse before that retention period ends.
- Avoid changing the API key or system prompt while a job is queued: both are read when the worker runs. The worker also rechecks the submitting user's permissions.

The API receives a strict schema containing `title`, `content_html`, `excerpt`, `seo_title`, `meta_description`, `keywords`, and `faq`. FAQ answers are appended to the article as text, with the questions as headings. The word count remains an approximate target. The client limits output to 12,000 completion tokens and the HTTP response to 1 MiB. Incomplete, refused, or incorrectly structured responses do not create a post.

### Errors and Duplicate Submissions

- Error messages distinguish key/permission issues, quota/rate limits, connection problems, timeouts, incompatible requests, and invalid responses.
- Upstream error messages are never passed through to the user.
- There are **no automatic retries**, to avoid unintended additional costs.
- Single-use tokens are valid for 30 minutes; successfully queuing a job consumes its form token. A new attempt receives a new token.
- Atomic database leases serialize queued and running generation requests per site. Expired leases are replaced using compare-and-swap; a stale request cannot delete or overwrite a new owner's lease. Queue leases expire after fifteen minutes, and worker leases after five minutes.
- Queued jobs older than ten minutes are rejected without calling OpenAI. Duplicate cron deliveries do not repeat a claimed job. Failed or interrupted jobs are not automatically retried.
- Job records and per-user status pointers use non-autoloaded database options, so clearing or evicting the object cache does not discard queued work or its result. Database compare-and-swap claims also prevent stale queued reads from repeating an API call. Outstanding transient-backed jobs from older versions are adopted when accessed.
- If a worker is terminated abruptly, the status eventually reports an unknown outcome. Check the post list before resubmitting. Only when there are no active workers, administrators can clear orphaned leases with `wp option delete doag_generation_lease` and `wp option delete doag_generation_worker`.
- After a browser or proxy interruption, check the post list first. A server-side request may still have completed; even a timeout can incur API costs.

### How To: Troubleshoot Generation

| Symptom | What to check or do |
|---|---|
| Article Generator is missing or access is denied | Use an administrator account with the capabilities listed under Requirements. Editors cannot use the generator by default. |
| A valid key was saved, but the plugin reports a missing or rejected key | Check OpenAI project access and billing. If the API-key constant is defined in `wp-config.php`, correct it there; it overrides the database key even when empty. |
| OpenAI rejects the request | Check that the exact model identifier is available to your project and supports Chat Completions with JSON Schema structured output. Clear temperature and try again after checking for an existing post. |
| Rate limit or quota reached | Check API billing, credits, and project limits in OpenAI; a ChatGPT subscription is separate. Wait before manually submitting again. |
| The page stays queued or generating | Check WordPress cron and hosting worker timeouts. Automatic page refresh only displays status; it is not a replacement for a working cron runner. See the commands below. |
| Response is incomplete or times out | Check existing posts first. Request fewer words or a simpler outline on a new attempt; the previous API request may still have incurred costs. |
| SEO title or description is not visible on the website | These are stored as plugin metadata only. Copy them into your SEO plugin manually or implement an integration using the documented hooks. |

For administrators with **WP-CLI**, run these checks from the WordPress installation directory (on Multisite, add `--url=https://your-site.example` to target the correct site):

```bash
wp cron test
wp cron event list --fields=hook,next_run_gmt,next_run_relative
```

Look for `doag_generate_article` while a job is waiting. An event may disappear from the list once a worker starts, so its absence alone does not prove failure. If normal cron spawning is disabled intentionally, verify that your external runner is executing due events frequently enough to start jobs within ten minutes.

After checking **Posts → All Posts** and confirming that no worker is already running, you can manually run a queued generation event:

```bash
wp cron event run doag_generate_article --due-now
```

This command runs real queued work and **can incur OpenAI API costs**; it is not a connection test. It does not create a new job or retry a failed one. Jobs already ten minutes old are rejected without an API call. Once cron is fixed and the old job has failed or expired, review the restored brief and submit a new request manually if needed. Do not delete leases while workers may still be active.

## SEO Metadata

The following values are stored:

| Meta Key | Value |
|---|---|
| `_doag_seo_title` | String; empty if not requested |
| `_doag_meta_description` | String; empty if not requested |
| `_doag_keywords` | Array of sanitized strings |

These values are not automatically written to the page head or copied into Yoast, Rank Math, or SEOPress fields. `DOAG\SEO_Adapter` and `DOAG\Post_Meta_Adapter` provide a small extension boundary for future adapters; a post-creation action is also available. No dedicated SEO frontend output or metadata meta box is included.

## Architecture

| Module | Responsibility |
|---|---|
| Main file / `Plugin` | Loading and composing components |
| `Settings` / `Admin\Settings_Page` | Defaults, secret access, Settings API, and rendering |
| `Article_Input` | Bounded and sanitized form inputs, status validation |
| `Prompt_Builder` | System/user prompts and JSON schema |
| `OpenAI_Client` | WordPress HTTP API, transport, and response validation |
| `Article_Validator` | Data types, lengths, HTML and text sanitization |
| `Article_Service` | Permissions, generation, post creation, and hooks |
| `Post_Meta_Adapter` | Plugin-owned SEO metadata |
| `Generation_Lock` | Ownership-aware atomic database leases with expiry |
| `Job_Store` | Durable, non-autoloaded job records, legacy adoption, and retention cleanup |
| `Background_Generator` | Single-use submissions, cron scheduling, user context, job status, and exception containment |
| `Admin\Generator_Page` | Form, nonce, queue submission, and result display |

There are no custom database tables or public AJAX/REST endpoints. Background jobs use WordPress cron and non-autoloaded options; disposable form tokens use transients. The namespace is `DOAG`; the text domain is `direct-openai-article-generator`.

## Security

- Settings require `manage_options`; generation additionally requires post capabilities. Private posts require `publish_posts`.
- Settings API nonce or a dedicated form nonce, user-bound single-use tokens, and a server-side status allowlist.
- Sanitization, type/length validation, and context-specific escaping of admin output.
- JSON validation before persistence; `wp_kses_post()` plus a stricter HTML allowlist. Only basic article tags and HTTP/HTTPS/mailto links are allowed; no JavaScript, event attributes, frames, images, styles, or forms.
- Shortcode brackets are stored as entities, and block comments are removed. Filter output is validated again before persistence.
- Fixed HTTPS endpoint, TLS verification, and no HTTP redirects carrying the Authorization header.
- No key in HTML, scripts, or displayed error messages; no plugin logs, even with `WP_DEBUG` enabled.
- API calls are exclusively server-side. The frontend does not load any plugin scripts.

Other installed plugins and server-side HTTP debuggers operate within the same trust boundary as WordPress. They can technically access options, constants, and HTTP hooks; review their logging separately. Custom extension filters are trusted PHP code and can change request behavior.

## Privacy

**When generating an article, all completed editorial fields and the configured system prompt are sent directly to OpenAI.** The key is transmitted in the Authorization header for authentication. OpenAI also necessarily sees the WordPress server's IP address. The plugin does not send the user's email address, WordPress user ID, or existing posts as additional prompt data.

Generated content is stored in the WordPress database. Non-autoloaded job options retain the editorial brief, submitting user ID, job status, and result/error, including to restore form values after errors. Job records and their per-user status pointers expire 30 minutes after enqueueing. A scheduled cleanup deletes them; reading an expired job also deletes it. Physical deletion can be delayed if cron is unavailable or the plugin is inactive. The configured system prompt and API key are read when the worker runs, not copied into the job. Short-lived transients contain only disposable form tokens/user IDs. There is no permanent prompt history.

The plugin itself:

- collects no telemetry,
- sends nothing to the plugin author,
- loads no external tracking scripts,
- sets no tracking cookies of its own,
- makes no hidden external requests.

`store: false` is sent to OpenAI. This does not replace your own review of OpenAI's current retention policies, project configuration, and contractual terms. References: [OpenAI API Data Controls](https://platform.openai.com/docs/guides/your-data), [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy/). The plugin does not automatically load these links.

## Hooks and Filters

All filters receive values **without the API key**. `$input` contains the sanitized form input. Changes to the model or request body must remain compatible with the OpenAI interface being used.

| Hook | Arguments | Timing / Return Value |
|---|---|---|
| `doag_system_prompt` | `string $prompt, array $input` | Complete system prompt, including formatting requirements; return a string |
| `doag_user_prompt` | `string $prompt, array $input` | User prompt; return a string |
| `doag_model` | `string $model, array $input` | Model in the request; return a string |
| `doag_openai_request_body` | `array $body, array $input` | JSON body before the HTTP call; return an array; no headers |
| `doag_generated_article_data` | `array $article, array $input` | After initial validation; validated again afterward; preserve the schema |
| `doag_article_created` | `int $post_id, array $article, array $input` | Action after post creation and SEO persistence |

Example in a custom site plugin:

```php
add_filter( 'doag_system_prompt', function ( $prompt, $input ) {
    return $prompt . "\nUse short paragraphs and avoid promotional claims.";
}, 10, 2 );

add_action( 'doag_article_created', function ( $post_id, $article, $input ) {
    update_post_meta( $post_id, '_my_editorial_review_required', '1' );
}, 10, 3 );
```

## Deactivation and Uninstallation

Deactivation preserves all data. **Deleting/uninstalling** removes the plugin settings, including the database key, lease/legacy lock options, durable job records, and status pointers, and unschedules generation and job-cleanup events on all sites in a Multisite installation. Generated posts and their post metadata are explicitly preserved. The `wp-config.php` constant also remains unchanged. Short-lived form and legacy job transients expire within 30 minutes and are removed by WordPress's normal transient cleanup; a persistent object cache manages its own expiration times.

## Development and Local Testing

```bash
composer install
composer lint
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Composer is required only for development tools. `phpcs.xml.dist` checks WordPress Coding Standards, including security/i18n, and PHPCompatibilityWP for PHP 8.1 and later. Only formal PHPDoc comment requirements are excluded; native types remain authoritative. `composer.lock` pins the tool versions.

### Offline Integration Test

Set up a **disposable local WordPress test installation** with an administrator and activate the plugin. Do not define an API key constant. Then run from the WordPress directory:

```bash
wp plugin activate direct-openai-article-generator
wp eval-file wp-content/plugins/direct-openai-article-generator/tests/integration.php
```

The test uses real WordPress functions and a real test database, but intercepts all HTTP calls through `pre_http_request`. It requires no real OpenAI key and incurs no API costs. Test posts are deleted and settings restored in the `finally` block. Test data may remain if the process is terminated abruptly. The tests are included in the source repository, not the release ZIP.

Checks cover permissions, the prohibited publish status, key exposure/precedence, request parameters, HTTP/JSON/transport errors, all three allowed statuses, metadata, FAQ bracket escaping, backslashes, XSS/shortcode sanitization, revalidation of filter output, insertion failures, lease contention/stale readers, background scheduling, duplicate delivery, user-context restoration, expired jobs, and safe exception handling. Job-storage regressions cover non-autoloaded options, cache eviction, failed durable claims, cleanup scheduling failures, retention cleanup, protection of newer jobs, and legacy job adoption. No PHPUnit bootstrap or additional test framework is required. Tests invoke workers directly, without requiring a live cron runner. Run only on a disposable site: cache-eviction checks flush its object cache.

### Manual Admin Test

1. Save settings as an administrator; the HTML source must not contain the saved key.
2. Access the generator and settings page as an editor/subscriber: access must be denied.
3. Submit the form with a missing/invalid nonce: it must be rejected with HTTP 403.
4. Submit the prohibited `publish` status or an invalid length: the request must be rejected and inputs preserved.
5. Queue a short draft with a valid model; the POST must redirect promptly without waiting for OpenAI. Verify pending status, then the edit link, content, and metadata after cron processes the job.
6. Resubmit the same POST request: the single-use token must be rejected.
7. Test an invalid key, empty temperature, and an incompatible model; verify that errors are static and contain no secret.
8. Delete the plugin: settings must be removed, while posts and SEO metadata are preserved.
9. Disable cron temporarily, submit a job, and confirm that no API request occurs in the admin request. After ten minutes, running the queued event must reject it without an API call.
10. Terminate a running worker in a disposable installation. Verify that status eventually reports the uncertain outcome, expired leases allow a later submission, and no automatic retry occurs.
11. On a disposable installation, downgrade PHP below 8.1 with the plugin already active. Frontend requests should still load; the admin must show the PHP requirement notice.

Live API tests require your own OpenAI key and incur costs. The documented automated test does not replace testing a live model or verifying your own hosting environment.

### Translations

The English interface is fully translatable; a POT template is available in `languages/`. Custom PO/MO files can be placed there, for example `direct-openai-article-generator-de_DE.mo`.

```bash
wp i18n make-pot . languages/direct-openai-article-generator.pot --exclude=vendor,tests
```

### Building a Release ZIP

After committing, run from the plugin repository:

```bash
mkdir -p build
git archive --format=zip --prefix=direct-openai-article-generator/ \
  -o build/direct-openai-article-generator-1.0.0.zip HEAD
```

`.gitattributes` excludes development files from the archive. The ZIP contains a top-level plugin folder and can be uploaded directly through WordPress.

## Roadmap

Not yet implemented:

- Gutenberg sidebar and direct block editor integration
- Revision of existing posts
- Automatic internal linking
- Image generation and Media Library integration
- Recurring scheduled generation and batch processing
- Custom prompt templates and multiple OpenAI configurations
- Yoast, Rank Math, and SEOPress adapters
- WooCommerce product descriptions and custom post types
- REST API and WP-CLI generation commands
- Usage/cost overview

## License

GPL-2.0-or-later. See [LICENSE](LICENSE). The plugin includes no external runtime libraries or telemetry.
