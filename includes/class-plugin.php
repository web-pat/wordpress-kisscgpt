<?php
/** Plugin composition root. @package DirectOpenAIArticleGenerator */

namespace DOAG;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		add_action( 'init', array( self::class, 'translations' ) );
		$settings = new Settings();
		$service  = new Article_Service( new OpenAI_Client( $settings ), new Post_Meta_Adapter() );
		( new Admin\Settings_Page( $settings ) )->register();
		( new Admin\Generator_Page( $settings, $service ) )->register();
	}

	public static function translations(): void {
		load_plugin_textdomain( 'direct-openai-article-generator', false, dirname( plugin_basename( DOAG_FILE ) ) . '/languages' );
	}
}
