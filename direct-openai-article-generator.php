<?php
/**
 * Plugin Name: Direct OpenAI Article Generator
 * Description: Generate articles directly through the OpenAI API and save them as non-public drafts or reviewed posts.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: Direct OpenAI Article Generator contributors
 * License: GPL-2.0-or-later
 * Text Domain: direct-openai-article-generator
 * Domain Path: /languages
 *
 * @package DirectOpenAIArticleGenerator
 */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

define( 'DOAG_VERSION', '1.0.0' );
define( 'DOAG_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-article-input.php';
require_once __DIR__ . '/includes/class-prompt-builder.php';
require_once __DIR__ . '/includes/class-openai-client.php';
require_once __DIR__ . '/includes/class-article-validator.php';
require_once __DIR__ . '/includes/interface-seo-adapter.php';
require_once __DIR__ . '/includes/class-post-meta-adapter.php';
require_once __DIR__ . '/includes/class-article-service.php';
require_once __DIR__ . '/admin/class-settings-page.php';
require_once __DIR__ . '/admin/class-generator-page.php';
require_once __DIR__ . '/includes/class-plugin.php';

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
